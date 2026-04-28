const { defineConfig, devices } = require('@playwright/test');

const BASE_URL = process.env.E2E_BASE_URL || 'http://127.0.0.1:8765';

module.exports = defineConfig({
    testDir: './tests',
    fullyParallel: false,
    workers: 1,
    retries: 0,
    reporter: process.env.CI ? 'github' : 'list',
    timeout: 30_000,
    expect: { timeout: 5_000 },

    use: {
        baseURL: BASE_URL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        video: 'retain-on-failure',
    },

    projects: [
        {
            name: 'chromium',
            use: { ...devices['Desktop Chrome'] },
        },
    ],

    webServer: process.env.E2E_NO_WEBSERVER ? undefined : {
        command: 'php -d variables_order=EGPCS -S 127.0.0.1:8765 -t ../public router.php',
        url: BASE_URL,
        reuseExistingServer: false,
        timeout: 60_000,
        stdout: 'pipe',
        stderr: 'pipe',
        env: {
            ...process.env,
            APP_ENV: 'test_e2e',
            APP_DEBUG: '0',
        },
    },
});
