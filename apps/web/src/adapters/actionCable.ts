type Identifier = { pubsub_token: string; account_id: number; user_id: number };
type Callbacks = { received?: (message: unknown) => void; connected?: () => void; disconnected?: () => void };

export const createConsumer = () => {
  let active = true;
  let connected = true;
  const timers = new Set<ReturnType<typeof setTimeout>>();
  const subscriptions = {
    create(identifier: Identifier, callbacks: Callbacks) {
      let cursor = 0;
      const poll = async () => {
        if (!active) return;
        try {
          const query = new URLSearchParams({ pubsub_token: identifier.pubsub_token, account_id: String(identifier.account_id), user_id: String(identifier.user_id), after: String(cursor) });
          const response = await fetch(`/api/cable/events?${query}`);
          if (!response.ok) throw new Error('Realtime subscription failed');
          const body = await response.json();
          cursor = body.cursor;
          body.events.forEach((item: { event: string; data: unknown }) => callbacks.received?.({ event: item.event, data: item.data }));
          if (!connected) callbacks.connected?.();
          connected = true;
        } catch {
          if (connected) callbacks.disconnected?.();
          connected = false;
        } finally {
          if (active) { const timer = setTimeout(poll, 1000); timers.add(timer); }
        }
      };
      void poll();
      return { perform(action: string) { if (action === 'update_presence') void fetch('/api/cable/presence', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ pubsub_token: identifier.pubsub_token }) }); } };
    },
  };
  return { subscriptions, connection: { isOpen: () => connected }, disconnect() { active = false; timers.forEach(clearTimeout); timers.clear(); connected = false; } };
};
