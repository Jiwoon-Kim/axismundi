/* Copyright 2024 The Android Open Source Project
 * SPDX-License-Identifier: Apache-2.0
 * Recipe: AndroidX CircularWavyProgressModifiers.kt at
 * 2e1a16243ae2c9284445a746f1d487c73af92623. JVM asset export adaptation.
 */
import androidx.graphics.shapes.CornerRounding
import androidx.graphics.shapes.Morph
import androidx.graphics.shapes.RoundedPolygon
import androidx.graphics.shapes.circle
import androidx.graphics.shapes.star
import com.google.gson.GsonBuilder
import java.nio.file.Files
import java.nio.file.Path
import kotlin.math.*

fun main(args: Array<String>) {
    val vertices = 9 // round(2 * PI * (48 / 2 - 4 / 2) / 15)
    val circle = RoundedPolygon.circle(numVertices = vertices).normalized()
    val star = RoundedPolygon.star(numVerticesPerRadius = vertices, innerRadius = .75f,
        rounding = CornerRounding(.35f, .4f), innerRounding = CornerRounding(.5f)).normalized()
    val morph = Morph(circle, star)
    val start = morph.asCubics(0f)
    val angle = atan2(start[0].anchor0Y - .5f, start[0].anchor0X - .5f).toDouble()
    fun coords(t: Float) = morph.asCubics(t).map { c ->
        listOf(c.anchor0X, c.anchor0Y, c.control0X, c.control0Y,
            c.control1X, c.control1Y, c.anchor1X, c.anchor1Y).chunked(2).flatMap { p ->
            val x = p[0] - .5; val y = p[1] - .5
            listOf(x * cos(-angle) - y * sin(-angle) + .5,
                x * sin(-angle) + y * cos(-angle) + .5).map { round(it * 1e7) / 1e7 }
        }
    }
    val data = linkedMapOf(
        "generatedBy" to "tools/generators/material-shapes/ExportProgress.kt; regenerate, do not edit",
        "verifiedBy" to "progress-indicators/__tests__/wave.test.cjs",
        "source" to "androidx@2e1a16243ae2c9284445a746f1d487c73af92623/CircularWavyProgressModifiers.kt",
        "engine" to "androidx.graphics:graphics-shapes-desktop:1.1.0",
        "vertices" to vertices, "circle" to coords(0f), "wave" to coords(1f))
    val text = GsonBuilder().setPrettyPrinting().create().toJson(data) + "\n"
    val root = Path.of(System.getProperty("axismundi.root", "/workspace"))
    val target = root.resolve("products/wordpress/plugins/axismundi/src/apps/frontend/components/progress-indicators/assets/circular-wave.json")
    if (args.contains("--check")) {
        val actual = if (Files.exists(target)) Files.readString(target) else ""
        if (actual != text) {
            val line = text.lines().zip(actual.lines()).indexOfFirst { it.first != it.second }
            error("$target differs at line ${if (line < 0) actual.lines().size + 1 else line + 1}; regenerate, do not edit output")
        }
    } else { Files.createDirectories(target.parent); Files.writeString(target, text) }
    println("progress geometry: $vertices vertices, ${start.size} matched cubics; ${if (args.contains("--check")) "unchanged" else "generated"}")
}
