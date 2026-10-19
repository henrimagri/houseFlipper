import test from 'node:test';
import assert from 'node:assert/strict';
import { objectLabel, roomLabel, visualizationModeLabel } from '../src/lib/propertyVisualization.ts';

test('visualization labels distinguish faithful models from fallback', () => {
  assert.equal(visualizationModeLabel({ faithful: true, level: 'a_model' }), 'Modelo 3D do imóvel');
  assert.equal(visualizationModeLabel({ faithful: false, level: 'photo_referenced' }), 'Cena referenciada nas fotos');
  assert.equal(visualizationModeLabel({ faithful: false, level: 'parametric_spec' }), 'Maquete paramétrica do anúncio');
  assert.equal(visualizationModeLabel({ faithful: false, level: 'fallback_gallery' }), 'Pré-visualização sem modelo 3D confiável');
  assert.equal(roomLabel('kitchen'), 'Cozinha');
  assert.equal(objectLabel('sofa'), 'Sofá');
});
