export const APPEARANCE_KEY = "axismundi.appearance";
export const brightnessOptions = ["system", "light", "dark"] as const;
export const contrastOptions = ["default", "high"] as const;
export const presets = [
  {id: "azure-blue", label: "Azure & Blue", colors: ["#0060ab", "#0047ff"]},
  {id: "rose-red", label: "Rose & Red", colors: ["#a10053", "#c00000"]},
  {id: "magenta-violet", label: "Magenta & Violet", colors: ["#a000a0", "#7139c6"]},
  {id: "cyan-orange", label: "Cyan & Orange", colors: ["#006a6a", "#994600"]},
] as const;

export interface Appearance {
  brightness: typeof brightnessOptions[number];
  contrast: typeof contrastOptions[number];
  accent: typeof presets[number]["id"];
}

function option<T extends string>(value: unknown, options: readonly T[], fallback: T): T {
  return options.find(item => item === value) ?? fallback;
}

export function readAppearance(raw: string | null): Appearance {
  let value: Partial<Appearance> | null = null;
  try { value = JSON.parse(raw ?? "null"); } catch { /* Invalid stored preference. */ }
  return {
    brightness: option(value?.brightness, brightnessOptions, "system"),
    contrast: option(value?.contrast, contrastOptions, "default"),
    accent: option(value?.accent, presets.map(preset => preset.id), "azure-blue"),
  };
}

export function appearanceAttributes(value: Appearance): Record<string, string> {
  return {
    brightness: value.brightness,
    contrast: value.contrast,
    accent: value.accent,
  };
}
