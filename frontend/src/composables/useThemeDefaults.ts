import { reactive } from 'vue'

// Default theme values matching NCE Theme Settings DocType defaults
export const defaultThemeColors = {
  primary_color: "#3B82F6",      // Blue
  secondary_color: "#10B981",    // Green
  accent_color: "#8B5CF6",        // Purple
  success_color: "#10B981",       // Green
  info_color: "#3B82F6",          // Blue
  warning_color: "#F59E0B",       // Orange
  danger_color: "#EF4444",        // Red
  text_color: "#1F2937",          // Dark gray
  heading_color: "#111827",       // Darker gray
  muted_color: "#6B7280",         // Medium gray
  link_color: "#3B82F6",          // Blue
  focus_color: "#3B82F6",         // Blue
  background_color: "#FFFFFF",    // White
  surface_color: "#F9FAFB",       // Light gray
  border_color: "#E5E7EB",        // Gray
  row_alt_color: "#F3F4F6",       // Light gray
  shadow_color: "#000000"         // Black
}

// Default theme settings beyond colors
export const defaultThemeSettings = {
  theme_name: "Default",
  font_family: "System Default",
  heading_font_family: "System Default",
  font_size: "14px",
  line_height: "normal",
  font_weight_body: "400",
  border_radius: "md",
  spacing_scale: "normal",
  shadow: "md",
  transition_speed: "normal",
  sidebar_width: "240px",
  container_max_width: "1200px"
}

// Combined defaults
export const defaultTheme = {
  ...defaultThemeColors,
  ...defaultThemeSettings
}

// Color scheme object for components that need it in this format
export const defaultColorScheme = {
  primary: defaultThemeColors.primary_color,
  secondary: defaultThemeColors.secondary_color,
  accent: defaultThemeColors.accent_color,
  success: defaultThemeColors.success_color,
  info: defaultThemeColors.info_color,
  warning: defaultThemeColors.warning_color,
  error: defaultThemeColors.danger_color,
  danger: defaultThemeColors.danger_color,
  text: defaultThemeColors.text_color,
  heading: defaultThemeColors.heading_color,
  muted: defaultThemeColors.muted_color,
  link: defaultThemeColors.link_color,
  focus: defaultThemeColors.focus_color,
  background: defaultThemeColors.background_color,
  surface: defaultThemeColors.surface_color,
  border: defaultThemeColors.border_color,
  rowAlt: defaultThemeColors.row_alt_color,
  shadow: defaultThemeColors.shadow_color
}


// Shade scales (50–950) for brand/status colors
const SHADE_COLORS = [
  "primary", "secondary", "accent", "success", "info", "warning", "danger"
] as const

const SHADE_STEPS = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950] as const

// Build shade entries for cssVariableMap
const shadeVariableMap: Record<string, string> = {}
for (const color of SHADE_COLORS) {
  for (const step of SHADE_STEPS) {
    shadeVariableMap[`${color}_color_${step}`] = `--nce-color-${color}-${step}`
  }
}

// CSS variable names mapping
export const cssVariableMap = {
  primary_color: "--nce-color-primary",
  secondary_color: "--nce-color-secondary",
  accent_color: "--nce-color-accent",
  success_color: "--nce-color-success",
  info_color: "--nce-color-info",
  warning_color: "--nce-color-warning",
  danger_color: "--nce-color-danger",
  text_color: "--nce-color-text",
  heading_color: "--nce-color-heading",
  muted_color: "--nce-color-muted",
  link_color: "--nce-color-link",
  focus_color: "--nce-color-focus",
  background_color: "--nce-color-bg",
  surface_color: "--nce-color-surface",
  border_color: "--nce-color-border",
  row_alt_color: "--nce-color-row-alt",
  shadow_color: "--nce-shadow-color",
  ...shadeVariableMap
}

/**
 * Composable to use theme with defaults
 * Falls back to default values if no theme is loaded
 */
export function useThemeDefaults(loadedTheme?: Record<string, any>) {
  const theme = reactive({
    ...defaultTheme,
    ...loadedTheme
  })

  const colorScheme = reactive({
    ...defaultColorScheme,
    ...(loadedTheme ? {
      primary: loadedTheme.primary_color || defaultColorScheme.primary,
      secondary: loadedTheme.secondary_color || defaultColorScheme.secondary,
      accent: loadedTheme.accent_color || defaultColorScheme.accent,
      success: loadedTheme.success_color || defaultColorScheme.success,
      info: loadedTheme.info_color || defaultColorScheme.info,
      warning: loadedTheme.warning_color || defaultColorScheme.warning,
      error: loadedTheme.danger_color || defaultColorScheme.error,
      danger: loadedTheme.danger_color || defaultColorScheme.danger,
      text: loadedTheme.text_color || defaultColorScheme.text,
      heading: loadedTheme.heading_color || defaultColorScheme.heading,
      muted: loadedTheme.muted_color || defaultColorScheme.muted,
      link: loadedTheme.link_color || defaultColorScheme.link,
      focus: loadedTheme.focus_color || defaultColorScheme.focus,
      background: loadedTheme.background_color || defaultColorScheme.background,
      surface: loadedTheme.surface_color || defaultColorScheme.surface,
      border: loadedTheme.border_color || defaultColorScheme.border,
      rowAlt: loadedTheme.row_alt_color || defaultColorScheme.rowAlt,
      shadow: loadedTheme.shadow_color || defaultColorScheme.shadow
    } : {})
  })

  // Function to get CSS variables object
  const getCSSVariables = () => {
    const vars: Record<string, string> = {}
    for (const [field, cssVar] of Object.entries(cssVariableMap)) {
      vars[cssVar] = theme[field] || defaultTheme[field]
    }
    return vars
  }

  // Function to apply theme to document root
  const applyToRoot = () => {
    const root = document.documentElement
    for (const [field, cssVar] of Object.entries(cssVariableMap)) {
      root.style.setProperty(cssVar, theme[field] || defaultTheme[field])
    }
  }

  return {
    theme,
    colorScheme,
    defaultTheme,
    defaultColorScheme,
    getCSSVariables,
    applyToRoot
  }
}

// Export a singleton instance with just defaults
export const themeDefaults = useThemeDefaults()
