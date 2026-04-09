import js from "@eslint/js";

export default [
    js.configs.recommended,
    {
        ignores: ["assets/vendor/**"],
    },
    {
        files: ["assets/**/*.{js,jsx}"],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: "module",
            parserOptions: {
                ecmaFeatures: { jsx: true }
            },
            globals: {
                window: "readonly",
                document: "readonly",
                console: "readonly",
                fetch: "readonly",
                setTimeout: "readonly",
                confirm: "readonly",
                alert: "readonly",
                requestAnimationFrame: "readonly",
                IntersectionObserver: "readonly",
                ResizeObserver: "readonly",
                HTMLElement: "readonly",
                Event: "readonly",
                CustomEvent: "readonly",
                btoa: "readonly",
                require: "readonly",
            }
        },
        rules: {
            "no-unused-vars": ["warn", { argsIgnorePattern: "^_", varsIgnorePattern: "^React$" }],
            "no-console": "off",
        }
    }
];
