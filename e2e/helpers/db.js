const { execSync } = require('node:child_process');
const path = require('node:path');

const PROJECT_ROOT = path.resolve(__dirname, '..', '..');

function resetDatabase() {
    execSync('php bin/console app:e2e:reset-db --env=test_e2e', {
        cwd: PROJECT_ROOT,
        stdio: ['ignore', 'ignore', 'inherit'],
        env: { ...process.env, APP_ENV: 'test_e2e' },
    });
}

module.exports = { resetDatabase };
