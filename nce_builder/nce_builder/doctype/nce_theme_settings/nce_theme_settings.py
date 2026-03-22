import json
import math
import os

import frappe
from frappe.model.document import Document

BORDER_RADIUS_MAP = {
	"none": "0",
	"sm": "0.125rem",
	"md": "0.375rem",
	"lg": "0.5rem",
	"full": "9999px",
}

SPACING_SCALE_MAP = {
	"tight": "0.75rem",
	"normal": "1rem",
	"relaxed": "1.5rem",
}

LINE_HEIGHT_MAP = {
	"tight": "1.25",
	"snug": "1.375",
	"normal": "1.5",
	"relaxed": "1.625",
	"loose": "2",
}

SHADOW_DEFS = {
	"none": [],
	"sm": [(0, 1, 3, 0, 0.12), (0, 1, 2, -1, 0.08)],
	"md": [(0, 4, 8, -1, 0.15), (0, 2, 4, -2, 0.1)],
	"lg": [(0, 10, 20, -3, 0.18), (0, 4, 8, -4, 0.1)],
	"xl": [(0, 20, 30, -5, 0.22), (0, 8, 12, -6, 0.12)],
	"2xl": [(0, 25, 50, -12, 0.3)],
	"3xl": [(0, 35, 60, -15, 0.35)],
}


def _hex_to_rgb(hex_color):
	hex_color = hex_color.lstrip("#")
	return tuple(int(hex_color[i : i + 2], 16) for i in (0, 2, 4))


# ── OKLCH color conversion (perceptually uniform) ──────────────────────────


def _srgb_to_linear(c):
	"""sRGB component (0-1) → linear light."""
	return c / 12.92 if c <= 0.04045 else ((c + 0.055) / 1.055) ** 2.4


def _linear_to_srgb(c):
	"""Linear light → sRGB component (0-1)."""
	return 12.92 * c if c <= 0.0031308 else 1.055 * (c ** (1 / 2.4)) - 0.055


def _hex_to_oklch(hex_color):
	"""Convert hex string → OKLCH (L: 0-1, C: 0-~0.4, h: 0-360)."""
	hex_color = hex_color.lstrip("#")
	r = _srgb_to_linear(int(hex_color[0:2], 16) / 255)
	g = _srgb_to_linear(int(hex_color[2:4], 16) / 255)
	b = _srgb_to_linear(int(hex_color[4:6], 16) / 255)

	# linear sRGB → LMS (Oklab M1, sRGB variant)
	l = 0.4122214708 * r + 0.5363325363 * g + 0.0514459929 * b
	m = 0.2119034982 * r + 0.6806995451 * g + 0.1073969566 * b
	s = 0.0883024619 * r + 0.2817188376 * g + 0.6299787005 * b

	# cube-root non-linearity
	l_ = math.copysign(abs(l) ** (1 / 3), l)
	m_ = math.copysign(abs(m) ** (1 / 3), m)
	s_ = math.copysign(abs(s) ** (1 / 3), s)

	# LMS′ → Oklab (M2)
	L = 0.2104542553 * l_ + 0.7936177850 * m_ - 0.0040720468 * s_
	a = 1.9779984951 * l_ - 2.4285922050 * m_ + 0.4505937099 * s_
	b_val = 0.0259040371 * l_ + 0.7827717662 * m_ - 0.8086757660 * s_

	C = math.sqrt(a * a + b_val * b_val)
	h = math.degrees(math.atan2(b_val, a)) % 360
	return L, C, h


def _oklch_to_hex(L, C, h):
	"""Convert OKLCH → clamped sRGB hex string."""
	a = C * math.cos(math.radians(h))
	b_val = C * math.sin(math.radians(h))

	# Oklab → LMS′ (M2 inverse)
	l_ = L + 0.3963377774 * a + 0.2158037573 * b_val
	m_ = L - 0.1055613458 * a - 0.0638541728 * b_val
	s_ = L - 0.0894841775 * a - 1.2914855480 * b_val

	# cube
	l = l_ * l_ * l_
	m = m_ * m_ * m_
	s = s_ * s_ * s_

	# LMS → linear sRGB (M1 inverse)
	r_lin = +4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s
	g_lin = -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s
	b_lin = -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s

	def clamp_byte(v):
		return round(255 * max(0.0, min(1.0, _linear_to_srgb(max(0.0, min(1.0, v))))))

	return f"#{clamp_byte(r_lin):02x}{clamp_byte(g_lin):02x}{clamp_byte(b_lin):02x}"


def _max_chroma_in_gamut(L, h, upper):
	"""Binary-search for the largest chroma at (L, h) that stays in sRGB."""
	lo, hi = 0.0, upper
	for _ in range(24):
		mid = (lo + hi) / 2
		a = mid * math.cos(math.radians(h))
		b_val = mid * math.sin(math.radians(h))
		l_ = L + 0.3963377774 * a + 0.2158037573 * b_val
		m_ = L - 0.1055613458 * a - 0.0638541728 * b_val
		s_ = L - 0.0894841775 * a - 1.2914855480 * b_val
		l = l_ * l_ * l_
		m = m_ * m_ * m_
		s = s_ * s_ * s_
		r = +4.0767416621 * l - 3.3077115913 * m + 0.2309699292 * s
		g = -1.2684380046 * l + 2.6097574011 * m - 0.3413193965 * s
		b = -0.0041960863 * l - 0.7034186147 * m + 1.7076147010 * s
		if r < -1e-6 or r > 1 + 1e-6 or g < -1e-6 or g > 1 + 1e-6 or b < -1e-6 or b > 1 + 1e-6:
			hi = mid
		else:
			lo = mid
	return lo


# OKLCH perceptual-lightness targets for each shade stop.
# Same stops as Tailwind (50–950). Lightness is in OKL space (0–1).
_SHADE_TARGETS = [
	(50, 0.97),
	(100, 0.93),
	(200, 0.87),
	(300, 0.78),
	(400, 0.68),
	(500, 0.57),
	(600, 0.48),
	(700, 0.39),
	(800, 0.31),
	(900, 0.23),
	(950, 0.16),
]


def _generate_shades(base_hex):
	"""Generate 11-stop shade scale (50–950) from a base hex colour.

	Uses OKLCH for perceptually uniform lightness steps.
	Keeps the base colour's hue constant, uses its chroma as the
	maximum, and gently desaturates at the light/dark extremes
	where sRGB gamut narrows and full chroma looks artificial.
	"""
	if not base_hex or len(base_hex) < 7:
		return []
	_L, base_C, h = _hex_to_oklch(base_hex)
	result = []
	for shade, target_l in _SHADE_TARGETS:
		# Cap to sRGB gamut at this lightness
		max_c = _max_chroma_in_gamut(target_l, h, base_C * 1.5)
		use_c = min(base_C, max_c)
		# Desaturate at extremes for subtle tints / deep shades
		if target_l >= 0.90:
			t = (target_l - 0.90) / 0.07  # 0→1 from L=0.90 to L=0.97
			use_c *= max(0.15, 1.0 - t * 0.85)
		elif target_l <= 0.25:
			t = (0.25 - target_l) / 0.09  # 0→1 from L=0.25 to L=0.16
			use_c *= max(0.5, 1.0 - t * 0.5)
		use_c = min(use_c, max_c)
		result.append((shade, _oklch_to_hex(target_l, use_c, h)))
	return result


def _build_shadow(level, color_hex):
	defs = SHADOW_DEFS.get(level, SHADOW_DEFS["md"])
	if not defs:
		return "none"
	r, g, b = _hex_to_rgb(color_hex) if color_hex else (0, 0, 0)
	parts = []
	for x, y, blur, spread, opacity in defs:
		parts.append(f"{x}px {y}px {blur}px {spread}px rgba({r}, {g}, {b}, {opacity})")
	return ", ".join(parts)


TRANSITION_MAP = {
	"fast": "150ms",
	"normal": "200ms",
	"slow": "300ms",
}

COLOR_FIELDS = {
	"primary_color": "color-primary",
	"secondary_color": "color-secondary",
	"accent_color": "color-accent",
	"success_color": "color-success",
	"info_color": "color-info",
	"warning_color": "color-warning",
	"danger_color": "color-danger",
	"text_color": "color-text",
	"heading_color": "color-heading",
	"muted_color": "color-muted",
	"link_color": "color-link",
	"focus_color": "color-focus",
	"background_color": "color-bg",
	"surface_color": "color-surface",
	"border_color": "color-border",
	"row_alt_color": "color-row-alt",
}


# Color fields that get a full Tailwind shade scale (50-950)
SHADE_SCALE_FIELDS = {
	"primary_color": ("color-primary", "color-primary"),
	"secondary_color": ("color-secondary", "color-secondary"),
	"accent_color": ("color-accent", "color-accent"),
	"success_color": ("color-success", "color-success"),
	"info_color": ("color-info", "color-info"),
	"warning_color": ("color-warning", "color-warning"),
	"danger_color": ("color-danger", "color-danger"),
}


class NCEThemeSettings(Document):
	def on_update(self):
		css = self._generate_css()
		self.db_set("compiled_css", css, update_modified=False)
		self._write_css_file(css)
		frappe.clear_cache()

	def _generate_css(self):
		lines = [":root {"]

		# ── NCE-namespaced variables (canonical) ──
		lines.append("")
		lines.append("\t/* ── NCE Theme: canonical variables ── */")

		for fieldname, var_name in COLOR_FIELDS.items():
			value = self.get(fieldname)
			if value:
				lines.append(f"\t--nce-{var_name}: {value};")

		# ── Shade scales for brand/status colors ──
		lines.append("")
		lines.append("\t/* ── Shade scales (50–950) ── */")

		for fieldname, (nce_name, std_name) in SHADE_SCALE_FIELDS.items():
			value = self.get(fieldname)
			if not value:
				continue
			shades = _generate_shades(value)
			for shade_num, shade_hex in shades:
				lines.append(f"\t--nce-{nce_name}-{shade_num}: {shade_hex};")

		if self.font_family and self.font_family != "System Default":
			font_value = f"'{self.font_family}', sans-serif"
		else:
			font_value = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
		lines.append(f"\t--nce-font-family: {font_value};")

		if self.heading_font_family and self.heading_font_family != "System Default":
			heading_font_value = f"'{self.heading_font_family}', sans-serif"
		else:
			heading_font_value = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif"
		lines.append(f"\t--nce-font-heading: {heading_font_value};")

		font_size = self.font_size or "14px"
		lines.append(f"\t--nce-font-size: {font_size};")

		font_weight = self.font_weight_body or "400"
		lines.append(f"\t--nce-font-weight: {font_weight};")

		lh = LINE_HEIGHT_MAP.get(self.line_height or "normal", "1.5")
		lines.append(f"\t--nce-line-height: {lh};")

		radius = BORDER_RADIUS_MAP.get(self.border_radius or "md", "0.375rem")
		lines.append(f"\t--nce-border-radius: {radius};")

		spacing = SPACING_SCALE_MAP.get(self.spacing_scale or "normal", "1rem")
		lines.append(f"\t--nce-spacing-base: {spacing};")

		shadow_color = self.shadow_color or "#000000"
		lines.append(f"\t--nce-shadow-color: {shadow_color};")
		shadow = _build_shadow(self.shadow or "md", shadow_color)
		lines.append(f"\t--nce-shadow: {shadow};")

		transition = TRANSITION_MAP.get(self.transition_speed or "normal", "200ms")
		lines.append(f"\t--nce-transition-speed: {transition};")

		if self.sidebar_width:
			lines.append(f"\t--nce-sidebar-width: {self.sidebar_width};")

		if self.container_max_width:
			cw = "100%" if self.container_max_width == "full" else self.container_max_width
			lines.append(f"\t--nce-container-max-width: {cw};")

		if self.tailwind_overrides:
			try:
				overrides = json.loads(self.tailwind_overrides)
				for key, value in overrides.items():
					lines.append(f"\t--nce-{key}: {value};")
			except (json.JSONDecodeError, TypeError):
				pass

		lines.append("}")

		if self.custom_css:
			lines.append("")
			lines.append(self.custom_css)

		return "\n".join(lines)

	def _write_css_file(self, css):
		app_path = frappe.get_app_path("nce_builder")
		css_dir = os.path.join(app_path, "public", "css")
		os.makedirs(css_dir, exist_ok=True)
		css_file = os.path.join(css_dir, "nce_theme.css")
		with open(css_file, "w") as f:
			f.write(css)
