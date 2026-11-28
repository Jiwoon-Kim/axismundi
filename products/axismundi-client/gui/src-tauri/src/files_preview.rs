//! File previews are re-encoded locally; the original blob is never exposed to the webview.
use std::{
    collections::HashMap,
    io::{Cursor, Seek, SeekFrom},
    sync::{Arc, Mutex},
    time::{Duration, Instant},
};

use image::{DynamicImage, ImageDecoder, ImageError, ImageFormat, ImageReader, Limits};
use ring::rand::{SecureRandom, SystemRandom};
use serde::{Deserialize, Serialize};
use tauri::{Manager, Runtime, http};

use crate::{connection::Connection, files};

#[cfg(test)]
mod tests {
    use super::*;
    use serde_json::{Value, json};
    use std::io::Write;

    fn render_bytes(bytes: &[u8], variant: Variant) -> Result<(Vec<u8>, u32, u32), Outcome> {
        let mut input = tempfile::tempfile().unwrap();
        input.write_all(bytes).unwrap();
        input.seek(SeekFrom::Start(0)).unwrap();
        render(&mut input, variant, Some("image/png"))
    }

    #[test]
    fn supported_inputs_become_bounded_png() {
        let image = image::DynamicImage::new_rgb8(400, 200);
        for format in [
            ImageFormat::Png,
            ImageFormat::Jpeg,
            ImageFormat::Gif,
            ImageFormat::WebP,
        ] {
            let mut bytes = Cursor::new(Vec::new());
            image.write_to(&mut bytes, format).unwrap();
            if format == ImageFormat::Jpeg {
                // Insert a synthetic EXIF APP1 segment; the PNG encoder must not carry it.
                bytes
                    .get_mut()
                    .splice(2..2, b"\xff\xe1\x00\x0aExif\0\0AB".iter().copied());
            }
            let (png, w, h) = render_bytes(bytes.get_ref(), Variant::Thumbnail).unwrap();
            assert_eq!((w, h), (256, 128), "{format:?}");
            assert_eq!(image::guess_format(&png).unwrap(), ImageFormat::Png);
            assert!(!png.windows(4).any(|chunk| chunk == b"eXIf"));
        }
    }

    #[test]
    fn jpeg_orientation_is_applied_before_thumbnailing() {
        let mut bytes = Cursor::new(Vec::new());
        DynamicImage::new_rgb8(400, 200)
            .write_to(&mut bytes, ImageFormat::Jpeg)
            .unwrap();
        // Little-endian EXIF orientation 6 (rotate 90 degrees clockwise).
        bytes.get_mut().splice(2..2,
            b"\xff\xe1\x00\x22Exif\0\0II\x2a\0\x08\0\0\0\x01\0\x12\x01\x03\0\x01\0\0\0\x06\0\0\0\0\0\0\0"
                .iter().copied());
        let (png, width, height) = render_bytes(bytes.get_ref(), Variant::Thumbnail).unwrap();
        assert_eq!((width, height), (128, 256));
        assert!(!png.windows(4).any(|chunk| chunk == b"eXIf"));
    }

    #[test]
    fn decode_allocation_limit_is_reported_as_too_large() {
        let mut limits = Limits::default();
        limits.max_alloc = Some(MAX_DECODE_BYTES);
        let error = limits.reserve(MAX_DECODE_BYTES + 1).unwrap_err();
        assert_eq!(
            serde_json::to_value(decode_error(error, Some("image/png"))).unwrap(),
            json!({"kind":"tooLarge","decodeMemory":true,"limit":MAX_DECODE_BYTES})
        );
    }

    #[test]
    fn rejects_svg_corrupt_images_and_oversized_dimensions() {
        assert!(matches!(
            render_bytes(b"<svg/>", Variant::Preview),
            Err(Outcome::Unsupported { .. })
        ));
        assert!(matches!(
            render_bytes(b"\x89PNG\r\n\x1a\n", Variant::Preview),
            Err(Outcome::Unsupported { .. })
        ));
        // Keep the encoded frame small while advertising a large logical screen.
        let mut gif = Cursor::new(Vec::new());
        image::DynamicImage::new_rgb8(2, 2)
            .write_to(&mut gif, ImageFormat::Gif)
            .unwrap();
        gif.get_mut()[6..8].copy_from_slice(&7000u16.to_le_bytes());
        gif.get_mut()[8..10].copy_from_slice(&7000u16.to_le_bytes());
        assert!(matches!(
            render_bytes(gif.get_ref(), Variant::Preview),
            Err(Outcome::TooLarge {
                observed: Excess::Pixels { .. },
                ..
            })
        ));
    }

    #[test]
    fn preview_refs_are_bound_to_webview_and_live_connection() {
        let key = Key {
            connection: 7,
            account: "a".into(),
            node: "n".into(),
            blob: "b".into(),
            variant: Variant::Preview,
        };
        let mut cache = Cache::default();
        let mut file = tempfile::NamedTempFile::new().unwrap();
        file.write_all(b"png").unwrap();
        cache.insert(
            key.clone(),
            Entry {
                file,
                size: 3,
                width: 1,
                height: 1,
                touched: Instant::now(),
            },
        );
        let Outcome::Ready { r#ref, .. } = cache.ready(&key, "main").unwrap() else {
            panic!()
        };
        let token = r#ref.rsplit('/').next().unwrap();
        assert_eq!(token.len(), 64);
        assert!(cache.read("missing", "main", |_| true).is_none());
        assert!(cache.read(token, "other", |_| true).is_none());
        assert_eq!(cache.read(token, "main", |id| id == 7).unwrap(), b"png");
        assert!(
            cache
                .ready(
                    &Key {
                        account: "other".into(),
                        ..key.clone()
                    },
                    "main"
                )
                .is_err()
        );
        assert!(
            cache
                .ready(
                    &Key {
                        blob: "new".into(),
                        ..key.clone()
                    },
                    "main"
                )
                .is_err()
        );
        assert!(cache.read(token, "main", |_| false).is_none());
        assert!(cache.read(token, "main", |_| true).is_none());
    }

    #[test]
    fn fixture_is_serialized_by_rust() {
        let fixture: Value =
            serde_json::from_str(include_str!("../../src/app/files/files.fixture.json")).unwrap();
        assert_eq!(
            fixture["preview"],
            json!({
                "ready": Outcome::Ready {r#ref: "axismundi-preview://localhost/example".into(), width: 256,height: 128,variant: Variant::Thumbnail},
                "unsupported": unsupported(Some("image/svg+xml")),
                "bytes": Outcome::TooLarge {observed: Excess::Bytes {size: MAX_BYTES+1},limit: MAX_BYTES},
                "pixels": Outcome::TooLarge {observed: Excess::Pixels {pixels: MAX_PIXELS+1},limit: MAX_PIXELS},
                "memory": Outcome::TooLarge {observed: Excess::Memory {decode_memory: true},limit: MAX_DECODE_BYTES},
                "failed": failed("Could not load preview"),
            })
        );
    }

    #[test]
    #[ignore = "requires the local Stalwart probe folder"]
    fn stalwart_preview() -> Result<(), Box<dyn std::error::Error>> {
        use axismundi_client_core::{
            http::{Client, Credentials},
            jmap::filenode,
        };
        let client = Client::without_redirects(Credentials {
            user: "admin@example.org".into(),
            secret: std::env::var("AXISMUNDI_FILES_TEST_PASSWORD")?,
        });
        let session = client.session("https://localhost:18881/jmap/session")?;
        let account = session
            .primary_account(filenode::CAPABILITY)
            .ok_or("No Files primary account")?;
        let list = |parent: Option<&str>| -> Result<Vec<Value>, Box<dyn std::error::Error>> {
            let page = filenode::query(&client, &session, account, parent, &[], 0, 100)?;
            let ids = page["ids"]
                .as_array()
                .ok_or("No ids")?
                .iter()
                .filter_map(Value::as_str)
                .collect::<Vec<_>>();
            if ids.is_empty() {
                return Ok(vec![]);
            }
            let response = filenode::get(&client, &session, account, &ids, false)?;
            Ok(response["list"]
                .as_array()
                .ok_or("No FileNode list")?
                .clone())
        };
        let root = list(None)?;
        let folder = root
            .iter()
            .find(|n| n["name"] == "axismundi-gui-probe")
            .ok_or("No probe folder")?;
        let nodes = list(Some(folder["id"].as_str().ok_or("No folder id")?))?;
        let node = nodes
            .iter()
            .find(|n| {
                n["@type"] == "File"
                    && n["name"].as_str().is_some_and(|name| {
                        [".png", ".webp"]
                            .iter()
                            .any(|ext| name.to_ascii_lowercase().ends_with(ext))
                    })
            })
            .ok_or("No PNG or WebP in the probe folder")?;
        let size = node["size"].as_u64().ok_or("No size")?;
        assert!(size <= MAX_BYTES);
        let start = Instant::now();
        let mut original = tempfile::tempfile()?;
        client.download_to(
            &session,
            account,
            node["blobId"].as_str().ok_or("No blobId")?,
            node["mediaType"]
                .as_str()
                .unwrap_or("application/octet-stream"),
            node["name"].as_str().ok_or("No name")?,
            size,
            &mut original,
        )?;
        original.seek(SeekFrom::Start(0))?;
        let (png, width, height) =
            render(&mut original, Variant::Preview, node["mediaType"].as_str())
                .map_err(|outcome| format!("{outcome:?}"))?;
        assert!(width.max(height) <= 1600);
        println!(
            "{size} input bytes -> {} PNG bytes, {width}x{height}, {:?}",
            png.len(),
            start.elapsed()
        );
        Ok(())
    }
}

pub(crate) const SCHEME: &str = "axismundi-preview";
const MAX_BYTES: u64 = 20_000_000;
const MAX_PIXELS: u64 = 40_000_000;
const MAX_DECODE_BYTES: u64 = 256 * 1024 * 1024;
const MAX_CACHE: u64 = 200_000_000;
const MAX_ENTRIES: usize = 512;
const MAX_REFERENCES: usize = 1024;
const MAX_OUTPUT: u64 = 12_000_000;
const TIME_BUDGET: Duration = Duration::from_secs(30);

#[derive(Clone, Copy, Debug, Deserialize, Serialize, PartialEq, Eq, Hash)]
#[serde(rename_all = "camelCase")]
pub(crate) enum Variant {
    Thumbnail,
    Preview,
}

impl Variant {
    fn edge(self) -> u32 {
        match self {
            Self::Thumbnail => 256,
            Self::Preview => 1600,
        }
    }
}

#[derive(Debug, Serialize)]
#[serde(tag = "kind", rename_all = "camelCase")]
pub(crate) enum Outcome {
    Ready {
        r#ref: String,
        width: u32,
        height: u32,
        variant: Variant,
    },
    Unsupported {
        #[serde(rename = "mediaType")]
        media_type: Option<String>,
    },
    TooLarge {
        #[serde(flatten)]
        observed: Excess,
        limit: u64,
    },
    Failed {
        error: String,
    },
}

#[derive(Debug, Serialize)]
#[serde(untagged)]
pub(crate) enum Excess {
    Bytes {
        size: u64,
    },
    Pixels {
        pixels: u64,
    },
    Memory {
        #[serde(rename = "decodeMemory")]
        decode_memory: bool,
    },
}

#[derive(Clone, PartialEq, Eq, Hash)]
struct Key {
    connection: u32,
    account: String,
    node: String,
    blob: String,
    variant: Variant,
}

struct Entry {
    file: tempfile::NamedTempFile,
    width: u32,
    height: u32,
    size: u64,
    touched: Instant,
}
struct Reference {
    key: Key,
    window: String,
    touched: Instant,
}

#[derive(Default)]
struct Cache {
    entries: HashMap<Key, Entry>,
    refs: HashMap<String, Reference>,
}

pub(crate) struct Previews {
    // Serializing download/decode work bounds memory and coalesces misses for the same key.
    worker: Arc<tokio::sync::Semaphore>,
    cache: Arc<Mutex<Cache>>,
}

impl Default for Previews {
    fn default() -> Self {
        Self {
            worker: Arc::new(tokio::sync::Semaphore::new(1)),
            cache: Arc::default(),
        }
    }
}

impl Cache {
    fn remove(&mut self, key: &Key) {
        self.entries.remove(key);
        self.refs.retain(|_, entry| &entry.key != key);
    }

    fn insert(&mut self, key: Key, entry: Entry) {
        let mut total = self.entries.values().map(|entry| entry.size).sum::<u64>() + entry.size;
        while total > MAX_CACHE || self.entries.len() >= MAX_ENTRIES {
            let Some((old, size)) = self
                .entries
                .iter()
                .min_by_key(|(_, e)| e.touched)
                .map(|(key, e)| (key.clone(), e.size))
            else {
                break;
            };
            self.remove(&old);
            total -= size;
        }
        self.entries.insert(key, entry);
    }

    fn ready(&mut self, key: &Key, window: &str) -> Result<Outcome, String> {
        let entry = self.entries.get_mut(key).ok_or("Preview expired.")?;
        entry.touched = Instant::now();
        let (width, height) = (entry.width, entry.height);
        let token = if let Some((token, item)) = self
            .refs
            .iter_mut()
            .find(|(_, item)| &item.key == key && item.window == window)
        {
            item.touched = Instant::now();
            token.clone()
        } else {
            if self.refs.len() >= MAX_REFERENCES {
                if let Some(token) = self
                    .refs
                    .iter()
                    .min_by_key(|(_, item)| item.touched)
                    .map(|(token, _)| token.clone())
                {
                    self.refs.remove(&token);
                }
            }
            let mut bytes = [0u8; 32];
            SystemRandom::new()
                .fill(&mut bytes)
                .map_err(|_| "Could not generate preview reference.")?;
            let token: String = bytes.iter().map(|b| format!("{b:02x}")).collect();
            self.refs.insert(
                token.clone(),
                Reference {
                    key: key.clone(),
                    window: window.into(),
                    touched: Instant::now(),
                },
            );
            token
        };
        let origin = if cfg!(any(target_os = "windows", target_os = "android")) {
            format!("http://{SCHEME}.localhost")
        } else {
            format!("{SCHEME}://localhost")
        };
        Ok(Outcome::Ready {
            r#ref: format!("{origin}/{token}"),
            width,
            height,
            variant: key.variant,
        })
    }

    fn read(
        &mut self,
        token: &str,
        window: &str,
        live: impl FnOnce(u32) -> bool,
    ) -> Option<Vec<u8>> {
        let item = self.refs.get(token)?;
        if item.window != window {
            return None;
        }
        let key = item.key.clone();
        if !live(key.connection) {
            self.remove(&key);
            return None;
        }
        let entry = self.entries.get_mut(&key)?;
        entry.touched = Instant::now();
        std::fs::read(entry.file.path()).ok()
    }
}

impl Previews {
    pub(crate) fn purge(&self, live: impl Fn(u32) -> bool) {
        if let Ok(mut cache) = self.cache.lock() {
            let dead: Vec<_> = cache
                .entries
                .keys()
                .filter(|key| !live(key.connection))
                .cloned()
                .collect();
            for key in dead {
                cache.remove(&key);
            }
        }
    }
}

pub(crate) fn serve<R: Runtime>(
    ctx: tauri::UriSchemeContext<'_, R>,
    request: http::Request<Vec<u8>>,
) -> http::Response<Vec<u8>> {
    let token = request.uri().path().strip_prefix('/').unwrap_or_default();
    let content = if request.method() == http::Method::GET
        && token.len() == 64
        && token.bytes().all(|b| b.is_ascii_hexdigit())
    {
        let previews = ctx.app_handle().state::<Previews>();
        let state = ctx.app_handle().state::<Connection>();
        previews.cache.lock().ok().and_then(|mut cache| {
            cache.read(token, ctx.webview_label(), |id| state.current(id).is_ok())
        })
    } else {
        None
    };
    let mut response = http::Response::new(content.unwrap_or_default());
    *response.status_mut() = if response.body().is_empty() {
        http::StatusCode::NOT_FOUND
    } else {
        http::StatusCode::OK
    };
    response.headers_mut().insert(
        http::header::CONTENT_TYPE,
        http::HeaderValue::from_static("image/png"),
    );
    response.headers_mut().insert(
        http::header::CACHE_CONTROL,
        http::HeaderValue::from_static("no-store"),
    );
    response.headers_mut().insert(
        "x-content-type-options",
        http::HeaderValue::from_static("nosniff"),
    );
    response
}

fn failed(error: impl ToString) -> Outcome {
    Outcome::Failed {
        error: error.to_string(),
    }
}
fn unsupported(media_type: Option<&str>) -> Outcome {
    Outcome::Unsupported {
        media_type: media_type.map(str::to_owned),
    }
}

fn decode_error(error: ImageError, media_type: Option<&str>) -> Outcome {
    if matches!(error, ImageError::Limits(ref error)
        if error.kind() == image::error::LimitErrorKind::InsufficientMemory)
    {
        return Outcome::TooLarge {
            observed: Excess::Memory {
                decode_memory: true,
            },
            limit: MAX_DECODE_BYTES,
        };
    }
    unsupported(media_type)
}

fn render(
    input: &mut std::fs::File,
    variant: Variant,
    media_type: Option<&str>,
) -> Result<(Vec<u8>, u32, u32), Outcome> {
    let mut reader = ImageReader::new(std::io::BufReader::new(&mut *input))
        .with_guessed_format()
        .map_err(|_| unsupported(media_type))?;
    let Some(
        format @ (ImageFormat::Png | ImageFormat::Jpeg | ImageFormat::Gif | ImageFormat::WebP),
    ) = reader.format()
    else {
        return Err(unsupported(media_type));
    };
    let (width, height) = reader
        .into_dimensions()
        .map_err(|_| unsupported(media_type))?;
    let pixels = u64::from(width) * u64::from(height);
    if pixels > MAX_PIXELS {
        return Err(Outcome::TooLarge {
            observed: Excess::Pixels { pixels },
            limit: MAX_PIXELS,
        });
    }
    input.seek(SeekFrom::Start(0)).map_err(failed)?;
    reader = ImageReader::with_format(std::io::BufReader::new(input), format);
    let mut limits = Limits::default();
    limits.max_image_width = Some(width);
    limits.max_image_height = Some(height);
    limits.max_alloc = Some(MAX_DECODE_BYTES);
    reader.limits(limits.clone());
    let mut decoder = reader
        .into_decoder()
        .map_err(|error| decode_error(error, media_type))?;
    // Match ImageReader::decode's accounting for the decoded pixel buffer.
    limits
        .reserve(decoder.total_bytes())
        .map_err(|error| decode_error(error, media_type))?;
    decoder
        .set_limits(limits)
        .map_err(|error| decode_error(error, media_type))?;
    let orientation = decoder
        .orientation()
        .map_err(|error| decode_error(error, media_type))?;
    let mut image =
        DynamicImage::from_decoder(decoder).map_err(|error| decode_error(error, media_type))?;
    image.apply_orientation(orientation);
    let edge = variant.edge();
    let image = if image.width() > edge || image.height() > edge {
        image.thumbnail(edge, edge)
    } else {
        image
    };
    let (width, height) = (image.width(), image.height());
    let mut png = Cursor::new(Vec::new());
    image.write_to(&mut png, ImageFormat::Png).map_err(failed)?;
    let png = png.into_inner();
    if png.len() as u64 > MAX_OUTPUT {
        return Err(failed("Encoded preview is too large."));
    }
    Ok((png, width, height))
}

#[tauri::command]
pub(crate) async fn files_preview<R: Runtime>(
    webview: tauri::Webview<R>,
    state: tauri::State<'_, Connection>,
    previews: tauri::State<'_, Previews>,
    connection_id: u32,
    account_id: Option<String>,
    node_id: String,
    variant: Variant,
) -> Result<Outcome, String> {
    let (active, session, account) = files::context(&state, connection_id, account_id.as_deref())?;
    let app = webview.app_handle().clone();
    let window = webview.label().to_owned();
    let cache = previews.cache.clone();
    let node = tauri::async_runtime::spawn_blocking({
        let (active, session, account, node_id, cache) = (
            active.clone(),
            session.clone(),
            account.clone(),
            node_id.clone(),
            cache.clone(),
        );
        move || match files::file_node(&active, &session, &account, &node_id) {
            Ok(node) => Ok(node),
            Err(error) => {
                if let Ok(mut cache) = cache.lock() {
                    let stale: Vec<_> = cache
                        .entries
                        .keys()
                        .filter(|key| {
                            key.connection == connection_id
                                && key.account == account
                                && key.node == node_id
                        })
                        .cloned()
                        .collect();
                    for key in stale {
                        cache.remove(&key);
                    }
                }
                Err(error)
            }
        }
    })
    .await
    .map_err(|_| "Preview lookup did not complete.")??;
    let blob = node["blobId"]
        .as_str()
        .ok_or("File has no stored blobId.")?
        .to_owned();
    let size = node["size"].as_u64().ok_or("File has no valid size.")?;
    let key = Key {
        connection: connection_id,
        account,
        node: node_id,
        blob,
        variant,
    };
    {
        let mut cache = cache.lock().map_err(|_| "Preview cache unavailable.")?;
        app.state::<Connection>().current(connection_id)?;
        let stale: Vec<_> = cache
            .entries
            .keys()
            .filter(|old| {
                old.connection == key.connection
                    && old.account == key.account
                    && old.node == key.node
                    && old.blob != key.blob
            })
            .cloned()
            .collect();
        for old in stale {
            cache.remove(&old);
        }
        if cache.entries.contains_key(&key) {
            return cache.ready(&key, &window);
        }
    }
    if size > MAX_BYTES {
        return Ok(Outcome::TooLarge {
            observed: Excess::Bytes { size },
            limit: MAX_BYTES,
        });
    }
    let permit = previews
        .worker
        .clone()
        .acquire_owned()
        .await
        .map_err(|_| "Preview worker unavailable.")?;
    tauri::async_runtime::spawn_blocking(move || {
        let _permit = permit;
        let state = app.state::<Connection>();
        {
            let mut cache = cache.lock().map_err(|_| "Preview cache unavailable.")?;
            state.current(connection_id)?;
            if cache.entries.contains_key(&key) {
                return cache.ready(&key, &window);
            }
        }
        let start = Instant::now();
        let name = node["name"].as_str().unwrap_or("file");
        let media_type = node["mediaType"].as_str();
        let build = || -> Result<Entry, Outcome> {
            let directory = app
                .path()
                .app_cache_dir()
                .map_err(failed)?
                .join("file-previews");
            std::fs::create_dir_all(&directory).map_err(failed)?;
            let mut original = tempfile::tempfile_in(&directory).map_err(failed)?;
            active
                .client
                .download_to(
                    &session,
                    &key.account,
                    &key.blob,
                    media_type.unwrap_or("application/octet-stream"),
                    name,
                    size,
                    &mut original,
                )
                .map_err(failed)?;
            original.seek(SeekFrom::Start(0)).map_err(failed)?;
            let (png, width, height) = render(&mut original, variant, media_type)?;
            // The HTTP client enforces its own deadline; this check does not interrupt decoding.
            if start.elapsed() > TIME_BUDGET {
                return Err(failed("Preview timed out."));
            }
            use std::io::Write;
            let mut file = tempfile::NamedTempFile::new_in(directory).map_err(failed)?;
            file.write_all(&png).map_err(failed)?;
            Ok(Entry {
                file,
                width,
                height,
                size: png.len() as u64,
                touched: Instant::now(),
            })
        };
        match build() {
            Ok(entry) => {
                let mut cache = cache.lock().map_err(|_| "Preview cache unavailable.")?;
                state.current(connection_id)?;
                cache.insert(key.clone(), entry);
                cache.ready(&key, &window)
            }
            Err(outcome) => Ok(outcome),
        }
    })
    .await
    .map_err(|_| "Preview command did not complete.")?
}
