export interface ColorShade {
  shade: number;
  hex: string;
}

// ── sRGB ↔ linear sRGB ─────────────────────────────────────────────────────

function srgbToLinear(c: number): number {
  return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
}

function linearToSrgb(c: number): number {
  return c <= 0.0031308 ? 12.92 * c : 1.055 * c ** (1 / 2.4) - 0.055;
}

// ── Hex ↔ OKLCH ─────────────────────────────────────────────────────────────

function hexToOklch(hex: string): { L: number; C: number; h: number } {
  const r = srgbToLinear(parseInt(hex.slice(1, 3), 16) / 255);
  const g = srgbToLinear(parseInt(hex.slice(3, 5), 16) / 255);
  const b = srgbToLinear(parseInt(hex.slice(5, 7), 16) / 255);

  // linear sRGB → LMS (Oklab M1, sRGB variant)
  const l = 0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b;
  const m = 0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b;
  const s = 0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b;

  // cube-root non-linearity
  const l_ = Math.cbrt(l);
  const m_ = Math.cbrt(m);
  const s_ = Math.cbrt(s);

  // LMS′ → Oklab (M2)
  const L = 0.2104542553 * l_ + 0.793617785 * m_ - 0.0040720468 * s_;
  const a = 1.9779984951 * l_ - 2.428592205 * m_ + 0.4505937099 * s_;
  const bVal = 0.0259040371 * l_ + 0.7827717662 * m_ - 0.808675766 * s_;

  const C = Math.sqrt(a * a + bVal * bVal);
  const h = ((Math.atan2(bVal, a) * 180) / Math.PI + 360) % 360;
  return { L, C, h };
}

function oklchToHex(L: number, C: number, h: number): string {
  const hRad = (h * Math.PI) / 180;
  const a = C * Math.cos(hRad);
  const bVal = C * Math.sin(hRad);

  // Oklab → LMS′ (M2 inverse)
  const l_ = L + 0.3963377774 * a + 0.2158037573 * bVal;
  const m_ = L - 0.1055613458 * a - 0.0638541728 * bVal;
  const s_ = L - 0.0894841775 * a - 1.291485548 * bVal;

  // cube
  const l = l_ * l_ * l_;
  const m = m_ * m_ * m_;
  const s = s_ * s_ * s_;

  // LMS → linear sRGB (M1 inverse)
  const rLin = +4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s;
  const gLin = -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s;
  const bLin = -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s;

  const clampByte = (v: number) =>
    Math.round(
      255 * Math.max(0, Math.min(1, linearToSrgb(Math.max(0, Math.min(1, v))))),
    );

  const rr = clampByte(rLin);
  const gg = clampByte(gLin);
  const bb = clampByte(bLin);
  return `#${rr.toString(16).padStart(2, "0")}${gg.toString(16).padStart(2, "0")}${bb.toString(16).padStart(2, "0")}`;
}

// ── Gamut mapping ───────────────────────────────────────────────────────────

/**
 * Binary-search for the largest chroma at (L, h) that stays inside sRGB.
 */
function maxChromaInGamut(L: number, h: number, upper: number): number {
  const hRad = (h * Math.PI) / 180;
  const cosH = Math.cos(hRad);
  const sinH = Math.sin(hRad);
  let lo = 0;
  let hi = upper;
  for (let i = 0; i < 24; i++) {
    const mid = (lo + hi) / 2;
    const a = mid * cosH;
    const bVal = mid * sinH;
    const l_ = L + 0.3963377774 * a + 0.2158037573 * bVal;
    const m_ = L - 0.1055613458 * a - 0.0638541728 * bVal;
    const s_ = L - 0.0894841775 * a - 1.291485548 * bVal;
    const l = l_ * l_ * l_;
    const m = m_ * m_ * m_;
    const s = s_ * s_ * s_;
    const r = +4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s;
    const g = -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s;
    const b = -0.0041960863 * l - 0.7034186147 * m + 1.707614701 * s;
    const eps = 1e-6;
    if (
      r < -eps ||
      r > 1 + eps ||
      g < -eps ||
      g > 1 + eps ||
      b < -eps ||
      b > 1 + eps
    ) {
      hi = mid;
    } else {
      lo = mid;
    }
  }
  return lo;
}

// ── Shade generation ────────────────────────────────────────────────────────

/**
 * OKLCH perceptual-lightness targets for each shade stop.
 * Same stops as Tailwind (50–950). Lightness is in OKL space (0–1).
 */
const SHADE_TARGETS: Array<{ shade: number; l: number }> = [
  { shade: 50, l: 0.97 },
  { shade: 100, l: 0.93 },
  { shade: 200, l: 0.87 },
  { shade: 300, l: 0.78 },
  { shade: 400, l: 0.68 },
  { shade: 500, l: 0.57 },
  { shade: 600, l: 0.48 },
  { shade: 700, l: 0.39 },
  { shade: 800, l: 0.31 },
  { shade: 900, l: 0.23 },
  { shade: 950, l: 0.16 },
];

/**
 * Generate an 11-stop shade scale (50–950) from a single base color.
 *
 * Uses OKLCH for perceptually uniform lightness steps.
 * Keeps the base colour's hue constant, uses its chroma as the
 * maximum, and gently desaturates at the light/dark extremes
 * where sRGB gamut narrows and full chroma looks artificial.
 */
export function generateShades(baseHex: string): ColorShade[] {
  if (!baseHex || !/^#[0-9A-Fa-f]{6}$/.test(baseHex)) return [];

  const { C: baseC, h } = hexToOklch(baseHex);

  return SHADE_TARGETS.map(({ shade, l: targetL }) => {
    // Cap to sRGB gamut at this lightness
    const maxC = maxChromaInGamut(targetL, h, baseC * 1.5);
    let useC = Math.min(baseC, maxC);

    // Desaturate at extremes for subtle tints / deep shades
    if (targetL >= 0.9) {
      const t = (targetL - 0.9) / 0.07; // 0→1 from L=0.90 to L=0.97
      useC *= Math.max(0.15, 1.0 - t * 0.85);
    } else if (targetL <= 0.25) {
      const t = (0.25 - targetL) / 0.09; // 0→1 from L=0.25 to L=0.16
      useC *= Math.max(0.5, 1.0 - t * 0.5);
    }
    useC = Math.min(useC, maxC);

    return {
      shade,
      hex: oklchToHex(targetL, useC, h),
    };
  });
}

/**
 * Determine whether a hex colour is visually dark (needs white text).
 * Uses OKLCH perceptual lightness instead of the crude sRGB formula.
 */
export function isDark(hex: string): boolean {
  if (!hex || hex.length < 7) return false;
  const { L } = hexToOklch(hex);
  return L < 0.55;
}
