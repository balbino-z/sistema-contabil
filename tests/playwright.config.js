// @ts-check
const { defineConfig, devices } = require('@playwright/test');

// BASE_URL permite rodar os mesmos testes no ambiente local ou na nuvem:
//   npx playwright test                                   (local, http://localhost:8088)
//   $env:BASE_URL="https://sua-app.onrender.com"; npx playwright test
module.exports = defineConfig({
  testDir: './e2e',
  fullyParallel: false,
  workers: 1,
  timeout: 60_000,
  retries: process.env.BASE_URL ? 1 : 0,
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL: process.env.BASE_URL || 'http://localhost:8088',
    screenshot: 'on',
    trace: 'retain-on-failure',
    locale: 'pt-BR',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
});
