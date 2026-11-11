export function notificationStatus(pending, sent) {
    if (pending > 0) {
        return {
            canSend: true,
            label: sent > 0 ? `Invia notifiche rimanenti (${pending})` : 'Invia notifiche',
        };
    }

    return {
        canSend: false,
        label: sent > 0 ? 'Notifiche inviate' : 'Nessun destinatario da notificare',
    };
}

export function notificationConfirmation(pending, sent, hasReplacedDocuments, afterPublication) {
    const text = afterPublication || sent === 0
        ? 'Inviare una email ai destinatari di questo lotto?'
        : `Inviare una email ai ${pending} destinatari non ancora notificati per questo lotto?`;

    return text + (!afterPublication && hasReplacedDocuments ? ' Le buste sostituite da rettifiche sono escluse.' : '');
}
