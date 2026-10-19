import test from 'node:test';
import assert from 'node:assert/strict';
import { appendOpportunityFields } from '../src/lib/opportunityForm.ts';

test('clearing an optional field is sent explicitly rather than omitted', () => {
  const payload = new FormData();
  appendOpportunityFields(payload, { title: 'Auditoria', city: '', asking_price: '', notes: '' });
  assert.equal(payload.get('city'), '');
  assert.equal(payload.get('asking_price'), '');
  assert.equal(payload.get('notes'), '');
  assert.equal(payload.get('title'), 'Auditoria');
});

test('zero and accented text are preserved in the multipart payload', () => {
  const payload = new FormData();
  appendOpportunityFields(payload, { title: 'São Paulo', asking_price: '0' });
  assert.equal(payload.get('title'), 'São Paulo');
  assert.equal(payload.get('asking_price'), '0');
});
