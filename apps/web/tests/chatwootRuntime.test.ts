import { beforeEach, describe, expect, it } from 'vitest';

import {
  installChatwootRuntime,
  readRuntimeConfig,
} from '../src/adapters/chatwootRuntime';

describe('Chatwoot runtime adapter', () => {
  beforeEach(() => {
    document.body.innerHTML = '';
  });

  it('installs Rails-compatible globals from inert JSON', () => {
    document.body.innerHTML = `
      <script id="twoteam-runtime-config" type="application/json">
        {"chatwootConfig":{"hostURL":"https://example.test","vapidPublicKey":[1,2,3]},"authToken":"secret"}
      </script>
    `;

    const target = {} as Window & typeof globalThis;
    installChatwootRuntime(readRuntimeConfig(), target);

    expect((target as typeof target & { authToken: string }).authToken).toBe(
      'secret',
    );
    const config = (
      target as typeof target & {
        chatwootConfig: { hostURL: string; vapidPublicKey: Uint8Array };
      }
    ).chatwootConfig;
    expect(config.hostURL).toBe('https://example.test');
    expect(config.vapidPublicKey).toEqual(new Uint8Array([1, 2, 3]));
  });

  it('ignores keys that are not part of the compatibility contract', () => {
    document.body.innerHTML = `
      <script id="twoteam-runtime-config" type="application/json">
        {"globalConfig":{"INSTALLATION_NAME":"Twoteam"},"unexpected":"nope"}
      </script>
    `;

    expect(readRuntimeConfig()).toEqual({
      globalConfig: { INSTALLATION_NAME: 'Twoteam' },
    });
  });

  it('rejects non-object payloads', () => {
    document.body.innerHTML = `
      <script id="twoteam-runtime-config" type="application/json">[]</script>
    `;

    expect(() => readRuntimeConfig()).toThrowError(
      'Twoteam runtime config must be a JSON object',
    );
  });
});
