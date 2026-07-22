#!/usr/bin/env node

const failures = [];
const requireValue = name => {
  const value = process.env[name];
  if (!value) failures.push(`${name} is required`);
  return value ?? '';
};

if (requireValue('APP_ENV') !== 'production') failures.push('APP_ENV must be production');
if (requireValue('APP_DEBUG') !== 'false') failures.push('APP_DEBUG must be false');
if (!requireValue('APP_URL').startsWith('https://')) failures.push('APP_URL must use HTTPS');
if (!/^base64:[A-Za-z0-9+/]{43}=$/.test(requireValue('APP_KEY'))) {
  failures.push('APP_KEY must be a generated Laravel base64 key');
}
if (requireValue('SESSION_SECURE_COOKIE') !== 'true') {
  failures.push('SESSION_SECURE_COOKIE must be true');
}
if (requireValue('LOG_LEVEL') === 'debug') failures.push('LOG_LEVEL must not be debug');

for (const name of ['DB_PASSWORD', 'REDIS_PASSWORD']) {
  const value = requireValue(name);
  if (value.length < 16 || /^(replace|password|twoteam)/i.test(value)) {
    failures.push(`${name} must be a non-placeholder secret of at least 16 characters`);
  }
}

if (failures.length) {
  console.error(failures.map(failure => `- ${failure}`).join('\n'));
  process.exit(1);
}

console.log('Production environment validation passed');
