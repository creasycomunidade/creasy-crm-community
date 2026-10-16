import { describe, expect, it } from 'vitest';
import { withReactionTargets } from '@/Pages/Inbox/reactionTargets';

describe('reaction message reference', () => {
    it('matches serialized WAHA reactions to raw NOWEB send IDs despite later messages', () => {
        const source = [{ id: 1, provider_message_id: 'waha:9:ABC', type: 'text', body: 'Pedido confirmado' },
            { id: 2, type: 'text', body: 'Outra mensagem' },
            { id: 3, type: 'reaction', payload: { reaction: { emoji: '🙏', target_provider_id: 'true_5547999999999@c.us_ABC' } } }];
        const result = withReactionTargets(source);
        expect(result[2].payload.quoted).toMatchObject({ message_id: 1, body: 'Pedido confirmado', type: 'text' });
        expect(source[2].payload.quoted).toBeUndefined();
    });
    it('resolves Evolution media and preserves references when the target is not loaded', () => {
        const quoted = { body: 'Mensagem antiga', message_id: 99 };
        const result = withReactionTargets([{ id: 1, type: 'image', provider_message_id: 'evogo:7:IMG', payload: { caption: 'Foto do pedido' } },
            { id: 2, payload: { reaction: { target_provider_id: 'IMG' } } },
            { id: 3, payload: { reaction: { target_provider_id: 'OTHER' }, quoted } }]);
        expect(result[1].payload.quoted).toMatchObject({ type: 'image', message_id: 1, body: 'Foto do pedido' });
        expect(result[2].payload.quoted).toEqual(quoted);
    });
});
