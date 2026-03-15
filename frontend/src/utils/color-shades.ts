export interface ColorShade {
	shade: number
	hex: string
}

function hexToHsl(hex: string): { h: number; s: number; l: number } {
	const r = parseInt(hex.slice(1, 3), 16) / 255
	const g = parseInt(hex.slice(3, 5), 16) / 255
	const b = parseInt(hex.slice(5, 7), 16) / 255
	const max = Math.max(r, g, b)
	const min = Math.min(r, g, b)
	let h = 0
	let s = 0
	const l = (max + min) / 2

	if (max !== min) {
		const d = max - min
		s = l > 0.5 ? d / (2 - max - min) : d / (max + min)
		switch (max) {
			case r:
				h = ((g - b) / d + (g < b ? 6 : 0)) / 6
				break
			case g:
				h = ((b - r) / d + 2) / 6
				break
			case b:
				h = ((r - g) / d + 4) / 6
				break
		}
	}

	return { h: h * 360, s: s * 100, l: l * 100 }
}

function hslToHex(h: number, s: number, l: number): string {
	s /= 100
	l /= 100
	const a = s * Math.min(l, 1 - l)
	const f = (n: number) => {
		const k = (n + h / 30) % 12
		const color = l - a * Math.max(Math.min(k - 3, 9 - k, 1), -1)
		return Math.round(255 * Math.max(0, Math.min(1, color)))
			.toString(16)
			.padStart(2, "0")
	}
	return `#${f(0)}${f(8)}${f(4)}`
}

const SHADE_TARGETS: Array<{ shade: number; l: number }> = [
	{ shade: 50, l: 97 },
	{ shade: 100, l: 94 },
	{ shade: 200, l: 86 },
	{ shade: 300, l: 77 },
	{ shade: 400, l: 66 },
	{ shade: 500, l: 50 },
	{ shade: 600, l: 40 },
	{ shade: 700, l: 32 },
	{ shade: 800, l: 24 },
	{ shade: 900, l: 17 },
	{ shade: 950, l: 10 },
]

/**
 * Generate an 11-stop shade scale (50–950) from a single base color.
 * Keeps hue constant, gently desaturates at extremes, and spaces
 * lightness so the base color anchors at ~500.
 */
export function generateShades(baseHex: string): ColorShade[] {
	if (!baseHex || !/^#[0-9A-Fa-f]{6}$/.test(baseHex)) return []

	const { h, s } = hexToHsl(baseHex)

	return SHADE_TARGETS.map(({ shade, l }) => {
		let sat = s
		if (shade <= 100) sat *= 0.75
		else if (shade >= 900) sat *= 0.8
		else if (shade <= 200) sat *= 0.9

		return {
			shade,
			hex: hslToHex(h, Math.min(sat, 100), l),
		}
	})
}

/**
 * Determine whether a hex colour is visually dark (needs white text).
 */
export function isDark(hex: string): boolean {
	if (!hex || hex.length < 7) return false
	const r = parseInt(hex.slice(1, 3), 16)
	const g = parseInt(hex.slice(3, 5), 16)
	const b = parseInt(hex.slice(5, 7), 16)
	return (r * 299 + g * 587 + b * 114) / 1000 < 128
}
