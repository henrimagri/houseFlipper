import test from 'node:test';
import assert from 'node:assert/strict';
import { emptyPropertyForm, propertyPayload } from '../src/lib/propertyForm.ts';

test('property payload converts numeric fields and normalizes address', () => {
  const payload = propertyPayload({ ...emptyPropertyForm, apelido: '  Casa QA  ', area_m2: '82.45', quartos: '2', banheiros: '1', vagas: '0', valor_aquisicao: '350000.25', valor_venda_alvo: '490000.75', cep: '01001-000', estado: 'sp' });
  assert.equal(payload.apelido, 'Casa QA');
  assert.equal(payload.area_m2, 82.45);
  assert.equal(payload.quartos, 2);
  assert.equal(payload.vagas, 0);
  assert.equal(payload.cep, undefined);
  assert.deepEqual(payload.endereco.cep, '01001000');
  assert.equal(payload.endereco.estado, 'SP');
});

test('property payload explicitly clears optional values', () => {
  const payload = propertyPayload({ ...emptyPropertyForm, apelido: 'Terreno' });
  assert.equal(payload.tipo, null);
  assert.equal(payload.area_m2, null);
  assert.equal(payload.data_aquisicao, null);
  assert.equal(payload.observacoes, null);
  assert.equal(payload.endereco.cidade, null);
  assert.equal(payload.endereco.pais, 'Brasil');
});
