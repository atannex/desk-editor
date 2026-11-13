import { defineConfig, devices } from '@playwright/test';

const db = new URL('./workbench/database/database.sqlite', import.meta.url).pathname;

export default defineConfig({
    testDir: 'tests/e2e',
    timeout: 45_000,
    fullyParallel: false,
    workers: 1,
    reporter: [['list']],
    use: {
        baseURL: 'http://127.0.0.1:8765',
        trace: 'retain-on-failure',
    },
    projects: [
        { name: 'chromium', use: { ...devices['Desktop Chrome'] } },
        { name: 'firefox', use: { ...devices['Desktop Firefox'] } },
        { name: 'mobile', use: { ...devices['Pixel 7'] } },
    ],
    webServer: process.env.PW_EXTERNAL_SERVER ? undefined : {
        command: `DB_CONNECTION=sqlite DB_DATABASE=${db} DESK_BLOG_ENABLED=true vendor/bin/testbench serve --port=8765`,
        url: 'http://127.0.0.1:8765/desk-editor/assets/desk-editor.css',
        reuseExistingServer: true,
        timeout: 60_000,
    },
});
