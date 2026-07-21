import { createConsumer } from '../src/adapters/actionCable';
import { afterEach, describe, expect, it, vi } from 'vitest';

describe('Laravel Action Cable adapter', () => {
  afterEach(() => { vi.useRealTimers(); vi.unstubAllGlobals(); });

  it('delivers envelopes, reports presence, and disconnects', async () => {
    vi.useFakeTimers();
    const fetchMock = vi.fn().mockResolvedValue({ ok: true, json: async () => ({ cursor: 4, events: [{ event: 'message.created', data: { id: 7 } }] }) });
    vi.stubGlobal('fetch', fetchMock);
    const received = vi.fn(); const consumer = createConsumer();
    const subscription = consumer.subscriptions.create({ pubsub_token: 'token', account_id: 1, user_id: 2 }, { received });
    await vi.waitFor(() => expect(received).toHaveBeenCalledWith({ event: 'message.created', data: { id: 7 } }));
    subscription.perform('update_presence');
    expect(fetchMock).toHaveBeenCalledWith('/api/cable/presence', expect.objectContaining({ method: 'POST' }));
    expect(consumer.connection.isOpen()).toBe(true); consumer.disconnect(); expect(consumer.connection.isOpen()).toBe(false);
  });

  it('notifies disconnect on polling failure', async () => {
    vi.useFakeTimers(); vi.stubGlobal('fetch', vi.fn().mockRejectedValue(new Error('offline'))); const disconnected = vi.fn();
    createConsumer().subscriptions.create({ pubsub_token: 'token', account_id: 1, user_id: 2 }, { disconnected });
    await vi.waitFor(() => expect(disconnected).toHaveBeenCalledOnce());
  });
});
