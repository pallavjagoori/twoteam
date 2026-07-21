const CONFIG_ELEMENT_ID = 'twoteam-runtime-config';

const runtimeKeys = [
  'analyticsConfig',
  'authToken',
  'browserConfig',
  'chatwootConfig',
  'chatwootPubsubToken',
  'chatwootSettings',
  'chatwootWebChannel',
  'errorLoggingConfig',
  'globalConfig',
  'portalConfig',
] as const;

type RuntimeKey = (typeof runtimeKeys)[number];
type RuntimeConfig = Partial<Record<RuntimeKey, unknown>>;

const isRecord = (value: unknown): value is Record<string, unknown> =>
  typeof value === 'object' && value !== null && !Array.isArray(value);

export const readRuntimeConfig = (
  documentRoot: Document = document,
): RuntimeConfig => {
  const element = documentRoot.getElementById(CONFIG_ELEMENT_ID);

  if (!element?.textContent?.trim()) return {};

  const parsed: unknown = JSON.parse(element.textContent);
  if (!isRecord(parsed)) {
    throw new TypeError('Twoteam runtime config must be a JSON object');
  }

  return Object.fromEntries(
    runtimeKeys
      .filter(key => Object.prototype.hasOwnProperty.call(parsed, key))
      .map(key => [key, parsed[key]]),
  );
};

export const installChatwootRuntime = (
  config: RuntimeConfig = readRuntimeConfig(),
  browserWindow: Window & typeof globalThis = window,
) => {
  for (const key of runtimeKeys) {
    if (config[key] === undefined) continue;

    let value = config[key];
    if (
      key === 'chatwootConfig' &&
      isRecord(value) &&
      Array.isArray(value.vapidPublicKey)
    ) {
      value = {
        ...value,
        vapidPublicKey: new Uint8Array(value.vapidPublicKey as number[]),
      };
    }

    Object.assign(browserWindow, { [key]: value });
  }
};

installChatwootRuntime();
