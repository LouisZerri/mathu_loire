const { execSync } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const PROJECT_ROOT = path.resolve(__dirname, '..', '..');
const MAILBOX_FILE = path.join(PROJECT_ROOT, 'var', 'e2e-mailbox.json');

function resetDatabase() {
    execSync('php bin/console app:e2e:reset-db --env=test_e2e', {
        cwd: PROJECT_ROOT,
        stdio: ['ignore', 'ignore', 'inherit'],
        env: { ...process.env, APP_ENV: 'test_e2e' },
    });
    if (fs.existsSync(MAILBOX_FILE)) {
        fs.unlinkSync(MAILBOX_FILE);
    }
}

function readMailbox() {
    if (!fs.existsSync(MAILBOX_FILE)) {
        return [];
    }
    return JSON.parse(fs.readFileSync(MAILBOX_FILE, 'utf-8'));
}

function findEmailTo(recipient) {
    return readMailbox().filter(m => m.to.includes(recipient));
}

module.exports = { resetDatabase, readMailbox, findEmailTo };
