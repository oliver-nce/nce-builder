import json
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


def _hex_to_hsl(hex_color):
	"""Convert hex to HSL (h: 0-360, s: 0-100, l: 0-100)."""
	hex_color = hex_color.lstrip("#")
	r = int(hex_color[0:2], 16) / 255
	g = int(hex_color[2:4], 16) / 255
	b = int(hex_color[4:6], 16) / 255
	mx, mn = max(r, g, b), min(r, g, b)
	h = 0.0
	s = 0.0
	l = (mx + mn) / 2
	if mx != mn:
		d = mx - mn
		s = d / (2 - mx - mn) if l > 0.5 else d / (mx + mn)
		if mx == r:
			h = ((g - b) / d + (6 if g < b else 0)) / 6
		elif mx == g:
			h = ((b - r) / d + 2) / 6
		else:
			h = ((r - g) / d + 4) / 6
	return h * 360, s * 100, l * 100


def _hsl_to_hex(h, s, l):
	"""Convert HSL (h: 0-360, s: 0-100, l: 0-100) to hex string."""
	s /= 100
	l /= 100
	a = s * min(l, 1 - l)

	def f(n):
		k = (n + h / 30) % 12
		color = l - a * max(min(k - 3, 9 - k, 1), -1)
		return round(255 * max(0, min(1, color)))

	return f"#{f(0):02x}{f(8):02x}{f(4):02x}"


# Tailwind-style shade targets — same as color-shades.ts
_SHADE_TARGETS = [
	(50, 97),
	(100, 94),
	(200, 86),
	(300, 77),
	(400, 66),
	(500, 50),
	(600, 40),
	(700, 32),
	(800, 24),
	(900, 17),
	(950, 10),
]


def _generate_shades(base_hex):
	"""Generate 11-stop shade scale (50-950) from a base hex color.

	Exact Python port of frontend/src/utils/color-shades.ts generateShades().
	"""
	if not base_hex or len(base_hex) < 7:
		return []
	h, s, _l = _hex_to_hsl(base_hex)
	result = []
	for shade, target_l in _SHADE_TARGETS:
		sat = s
		if shade <= 100:
			sat *= 0.75
		elif shade >= 900:
			sat *= 0.8
		elif shade <= 200:
			sat *= 0.9
		result.append((shade, _hsl_to_hex(h, min(sat, 100), target_l)))
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

		# ── Generic variables (cross-app standard) ──
		lines.append("")
		lines.append("\t/* ── Generic standard variables (used by all apps) ── */")

		# Colors
		primary = self.primary_color or "#3B82F6"
		secondary = self.secondary_color or "#10B981"
		text_color = self.text_color or "#1F2937"
		muted_color = self.muted_color or "#6B7280"
		heading_color = self.heading_color or "#111827"
		link_color = self.link_color or "#3B82F6"
		focus_color = self.focus_color or "#3B82F6"
		bg_color = self.background_color or "#FFFFFF"
		surface_color = self.surface_color or "#F9FAFB"
		border_color = self.border_color or "#E5E7EB"
		row_alt_color = self.row_alt_color or "#F3F4F6"
		success_color = self.success_color or "#10B981"
		warning_color = self.warning_color or "#F59E0B"
		danger_color = self.danger_color or "#EF4444"
		info_color = self.info_color or "#3B82F6"
		accent_color = self.accent_color or "#8B5CF6"

		# Compute primary-light (10% opacity tint of primary on white)
		pr, pg, pb = _hex_to_rgb(primary)
		primary_light = f"rgba({pr}, {pg}, {pb}, 0.1)"

		# Background & surface
		lines.append(f"\t--bg-page: {bg_color};")
		lines.append(f"\t--bg-surface: {surface_color};")
		lines.append(f"\t--bg-card: {surface_color};")
		lines.append(f"\t--bg-header: {primary};")
		lines.append(f"\t--primary-light: {primary_light};")
		lines.append(f"\t--row-alt: {row_alt_color};")

		# Text
		lines.append(f"\t--text-color: {text_color};")
		lines.append(f"\t--text-muted: {muted_color};")
		lines.append(f"\t--text-heading: {heading_color};")
		lines.append(f"\t--text-link: {link_color};")
		lines.append(f"\t--text-header: #FFFFFF;")

		# Semantic colors
		lines.append(f"\t--color-primary: {primary};")
		lines.append(f"\t--color-secondary: {secondary};")
		lines.append(f"\t--color-accent: {accent_color};")
		lines.append(f"\t--color-success: {success_color};")
		lines.append(f"\t--color-info: {info_color};")
		lines.append(f"\t--color-warning: {warning_color};")
		lines.append(f"\t--color-danger: {danger_color};")

		# Shade scales (standard Tailwind names)
		for fieldname, (nce_name, std_name) in SHADE_SCALE_FIELDS.items():
			value = self.get(fieldname)
			if not value:
				continue
			shades = _generate_shades(value)
			for shade_num, shade_hex in shades:
				lines.append(f"\t--{std_name}-{shade_num}: {shade_hex};")

		# Borders & inputs
		lines.append(f"\t--border-color: {border_color};")
		lines.append(f"\t--input-border: {border_color};")
		lines.append(f"\t--input-focus-border: {focus_color};")
		lines.append(f"\t--border-radius: {radius};")
		lines.append(f"\t--border-radius-sm: calc({radius} * 0.5);")

		# Typography
		lines.append(f"\t--font-family: {font_value};")
		lines.append(f"\t--font-heading: {heading_font_value};")
		lines.append(f"\t--font-size-base: {font_size};")
		font_size_num = float(font_size.replace("px", "")) if "px" in font_size else 14
		lines.append(f"\t--font-size-xs: {font_size_num * 0.75:.1f}px;")
		lines.append(f"\t--font-size-sm: {font_size_num * 0.875:.1f}px;")
		lines.append(f"\t--font-size-lg: {font_size_num * 1.125:.1f}px;")
		lines.append(f"\t--font-size-xl: {font_size_num * 1.25:.1f}px;")
		lines.append(f"\t--font-weight-normal: {font_weight};")
		bold_weight = max(int(font_weight) + 200, 600) if font_weight.isdigit() else 600
		lines.append(f"\t--font-weight-bold: {bold_weight};")
		lines.append(f"\t--line-height: {lh};")

		# Spacing
		lines.append(f"\t--spacing-base: {spacing};")
		spacing_num = float(spacing.replace("rem", "")) if "rem" in spacing else 1
		lines.append(f"\t--spacing-xs: {spacing_num * 0.25:.3f}rem;")
		lines.append(f"\t--spacing-sm: {spacing_num * 0.5:.3f}rem;")
		lines.append(f"\t--spacing-md: {spacing_num:.3f}rem;")
		lines.append(f"\t--spacing-lg: {spacing_num * 1.5:.3f}rem;")
		lines.append(f"\t--spacing-xl: {spacing_num * 2:.3f}rem;")

		# Shadow & transitions
		lines.append(f"\t--shadow: {shadow};")
		lines.append(f"\t--shadow-color: {shadow_color};")
		lines.append(f"\t--transition-speed: {transition};")

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
