import path from "node:path";
import { fileURLToPath } from "node:url";
import forms from "@tailwindcss/forms";
import typography from "@tailwindcss/typography";

const __dirname = path.dirname(fileURLToPath(import.meta.url));

export default {
  darkMode: ["selector", '[data-theme="dark"]'],
  content: [
    "./index.html",
    "./src/**/*.{vue,js,ts,jsx,tsx}",
    path.resolve(
      __dirname,
      "node_modules/frappe-ui/src/components/**/*.{vue,js,ts,jsx,tsx}",
    ),
  ],
  theme: {
    extend: {
      colors: {
        primary: "var(--nce-color-primary)",
        "primary-light": "var(--primary-light)",
        secondary: "var(--nce-color-secondary)",
        accent: "var(--nce-color-accent)",
        success: "var(--nce-color-success)",
        info: "var(--nce-color-info)",
        warning: "var(--nce-color-warning)",
        danger: "var(--nce-color-danger)",
        heading: "var(--nce-color-heading)",
        muted: "var(--nce-color-muted)",
        link: "var(--nce-color-link)",
        focus: "var(--nce-color-focus)",
        surface: "var(--nce-color-surface)",
        border: "var(--nce-color-border)",
        "row-alt": "var(--nce-color-row-alt)",
        "bg-page": "var(--nce-color-bg)",
        "bg-header": "var(--bg-header)",
        "text-header": "var(--text-header)",
        "input-border": "var(--input-border)",
        "input-focus": "var(--input-focus-border)",
      },
      textColor: {
        DEFAULT: "var(--nce-color-text)",
        heading: "var(--nce-color-heading)",
        muted: "var(--nce-color-muted)",
        link: "var(--nce-color-link)",
      },
      backgroundColor: {
        DEFAULT: "var(--nce-color-bg)",
        surface: "var(--nce-color-surface)",
        card: "var(--nce-color-surface)",
        header: "var(--bg-header)",
      },
      borderColor: {
        DEFAULT: "var(--nce-color-border)",
      },
      fontFamily: {
        sans: ["var(--nce-font-family)"],
        heading: ["var(--nce-font-heading)"],
      },
      fontSize: {
        xs: "calc(var(--nce-font-size) * 0.75)",
        sm: "calc(var(--nce-font-size) * 0.875)",
        base: "var(--nce-font-size)",
        lg: "calc(var(--nce-font-size) * 1.125)",
        xl: "calc(var(--nce-font-size) * 1.25)",
        "2xl": "calc(var(--nce-font-size) * 1.5)",
        "3xl": "calc(var(--nce-font-size) * 1.875)",
        "4xl": "calc(var(--nce-font-size) * 2.25)",
      },
      borderRadius: {
        DEFAULT: "var(--nce-border-radius)",
        sm: "calc(var(--nce-border-radius) * 0.5)",
        md: "var(--nce-border-radius)",
        lg: "calc(var(--nce-border-radius) * 1.5)",
        xl: "calc(var(--nce-border-radius) * 2)",
      },
      boxShadow: {
        DEFAULT: "var(--nce-shadow)",
        theme: "var(--nce-shadow)",
      },
      spacing: {
        xs: "calc(var(--nce-spacing-base) * 0.25)",
        sm: "calc(var(--nce-spacing-base) * 0.5)",
        md: "var(--nce-spacing-base)",
        lg: "calc(var(--nce-spacing-base) * 1.5)",
        xl: "calc(var(--nce-spacing-base) * 2)",
      },
      transitionDuration: {
        theme: "var(--nce-transition-speed)",
      },
    },
  },
  plugins: [forms, typography],
};
