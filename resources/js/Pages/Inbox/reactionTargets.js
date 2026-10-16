// Resolve only against messages already belonging to this conversation.
const aliases = value => {
    if (typeof value !== 'string' || !value) return [];
    const raw = value.replace(/^(?:waha|evolution_api|evogo):[0-9]+:/, '');
    const serialized = raw.match(/^(?:true|false)_[^_]+_([^_]+)(?:_.*)?$/);
    return [...new Set([value, raw, ...(serialized ? [serialized[1]] : [])])];
};

export function withReactionTargets(messages) {
    const byProvider = new Map();
    const byId = new Map();
    for (const message of messages) {
        if (message.type === 'reaction' || message.payload?.reaction) continue;
        byId.set(String(message.id), message);
        for (const id of aliases(message.provider_message_id)) byProvider.set(id, message);
    }
    return messages.map(message => {
        const payload = message.payload || {};
        if (!payload.reaction) return message;
        const targetId = payload.reaction.target_provider_id || payload.reaction.message_id || payload.quoted?.provider_id;
        const original = byId.get(String(payload.quoted?.message_id)) || aliases(targetId).map(id => byProvider.get(id)).find(Boolean);
        if (!original) return message;
        return { ...message, payload: { ...payload, quoted: {
            message_id: original.id, type: original.type, direction: original.direction,
            body: original.body || original.payload?.caption || original.payload?.filename || '',
        } } };
    });
}
