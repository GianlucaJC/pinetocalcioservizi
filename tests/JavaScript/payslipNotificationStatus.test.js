import assert from 'node:assert/strict';
import test from 'node:test';
import { notificationConfirmation, notificationStatus } from '../../resources/js/Pages/Admin/Payslips/notificationStatus.js';

test('notification action reflects unsent, partial, completed and empty batches', () => {
    assert.deepEqual(notificationStatus(4, 0), { canSend: true, label: 'Invia notifiche' });
    assert.deepEqual(notificationStatus(2, 3), { canSend: true, label: 'Invia notifiche rimanenti (2)' });
    assert.deepEqual(notificationStatus(0, 5), { canSend: false, label: 'Notifiche inviate' });
    assert.deepEqual(notificationStatus(0, 0), { canSend: false, label: 'Nessun destinatario da notificare' });
});

test('confirmation mentions corrections only for an existing batch with replaced documents', () => {
    assert.equal(notificationConfirmation(4, 0, false, false), 'Inviare una email ai destinatari di questo lotto?');
    assert.equal(notificationConfirmation(2, 3, false, false), 'Inviare una email ai 2 destinatari non ancora notificati per questo lotto?');
    assert.equal(notificationConfirmation(2, 3, true, false), 'Inviare una email ai 2 destinatari non ancora notificati per questo lotto? Le buste sostituite da rettifiche sono escluse.');
    assert.equal(notificationConfirmation(0, 0, false, true), 'Inviare una email ai destinatari di questo lotto?');
});
