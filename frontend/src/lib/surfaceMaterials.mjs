// Generated procedural patterns, not photographs or measured material scans.
export const SURFACE_TEXTURE_SIZE = 128;
const TAU = Math.PI * 2;
const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
const fract = value => value - Math.floor(value);
const presets = {
  wood: { meters: [1.2, 0.6], roughness: 0.65, bump: 0.0012 },
  laminate: { meters: [1.2, 0.6], roughness: 0.52, bump: 0.0006 },
  ceramic: { meters: [0.4, 0.4], roughness: 0.62, bump: 0.0015 },
  porcelain: { meters: [0.6, 0.6], roughness: 0.42, bump: 0.001 },
  marble: { meters: [1.2, 1.2], roughness: 0.36, bump: 0.0004 },
  paint: { meters: [0.5, 0.5], roughness: 0.88, bump: 0.00015 },
};

/** Pure, bounded CPU generation; also usable in tests without a DOM or WebGL. */
export function generateSurfaceMaps({ kind = 'paint', color = '#d9d3c8', finish, width = 1, height = 1 } = {}) {
  if (!Object.hasOwn(presets, kind)) kind = 'paint';
  if (typeof color !== 'string' || !/^#[0-9a-f]{6}$/i.test(color)) throw new TypeError('Surface color must be #RRGGBB.');
  if (![width, height].every(value => typeof value === 'number' && Number.isFinite(value) && value > 0 && value <= 1000)) {
    throw new RangeError('Surface dimensions must be positive meters, at most 1000.');
  }
  if (finish !== undefined && !['matte', 'satin', 'gloss', 'semigloss'].includes(finish)) throw new TypeError('Unsupported surface finish.');
  const preset = presets[kind];
  const roughnessValue = finish === undefined ? preset.roughness : { matte: 0.88, satin: 0.56, semigloss: 0.38, gloss: 0.24 }[finish];
  const rgb = [1, 3, 5].map(index => parseInt(color.slice(index, index + 2), 16));
  const size = SURFACE_TEXTURE_SIZE;
  const baseColor = new Uint8Array(size * size * 4);
  const bump = new Uint8Array(baseColor.length);
  const roughness = new Uint8Array(baseColor.length);
  for (let y = 0; y < size; y++) {
    for (let x = 0; x < size; x++) {
      const u = (x + 0.5) / size;
      const v = (y + 0.5) / size;
      // Periodic harmonics keep texture boundaries continuous; no random state.
      const fine = Math.sin(TAU * (u * 19 + v * 31)) * Math.cos(TAU * (u * 37 - v * 17));
      let shade = 1 + fine * 0.008;
      let relief = 0.5 + fine * 0.06;
      let joint = false;
      if (kind === 'wood' || kind === 'laminate') {
        const row = Math.floor(v * 3);
        const grain = Math.sin(TAU * (v * 45 + 0.32 * Math.sin(TAU * u * 2)));
        const boardU = fract(u + row / 3);
        joint = fract(v * 3) < 0.025 || boardU < 0.009;
        shade = 0.96 + grain * 0.055 + Math.sin(TAU * (u + row / 3)) * 0.025 + fine * 0.012;
        relief = 0.55 + grain * 0.07;
      } else if (kind === 'ceramic' || kind === 'porcelain') {
        joint = Math.min(u, v, 1 - u, 1 - v) < 0.008;
        shade = 0.99 + fine * 0.012;
      } else if (kind === 'marble') {
        const vein = Math.pow(Math.abs(Math.sin(TAU * (u * 2 + v + 0.24 * Math.sin(TAU * v * 3) + 0.1 * Math.cos(TAU * u * 2)))), 18);
        shade = 1 - vein * 0.19 + fine * 0.007;
        relief = 0.5 + fine * 0.02;
      }
      if (joint) { shade *= 0.76; relief = 0.08; }
      const index = (y * size + x) * 4;
      for (let channel = 0; channel < 3; channel++) {
        baseColor[index + channel] = Math.round(clamp(rgb[channel] * shade, 0, 255));
        bump[index + channel] = Math.round(clamp(relief, 0, 1) * 255);
        roughness[index + channel] = Math.round(clamp(roughnessValue + fine * 0.025 + (joint ? 0.12 : 0), 0.12, 0.98) * 255);
      }
      baseColor[index + 3] = bump[index + 3] = roughness[index + 3] = 255;
    }
  }
  return { kind, size, baseColor, bump, roughness, bumpScale: preset.bump,
    tileMeters: [...preset.meters], repeat: [width / preset.meters[0], height / preset.meters[1]] };
}

/** Each material owns its three maps: dispose maps as well as the material. */
export function createSurfaceMaterial(THREE, options = {}) {
  const maps = generateSurfaceMaps(options);
  const texture = (data, colorSpace) => {
    const result = new THREE.DataTexture(data, maps.size, maps.size, THREE.RGBAFormat);
    result.colorSpace = colorSpace;
    result.wrapS = result.wrapT = THREE.RepeatWrapping;
    result.repeat.set(...maps.repeat);
    result.magFilter = THREE.LinearFilter;
    result.minFilter = THREE.LinearMipmapLinearFilter;
    result.generateMipmaps = true;
    result.anisotropy = 4;
    result.needsUpdate = true;
    return result;
  };
  const material = new THREE.MeshStandardMaterial({
    color: '#ffffff', metalness: 0, roughness: 1,
    map: texture(maps.baseColor, THREE.SRGBColorSpace),
    bumpMap: texture(maps.bump, THREE.NoColorSpace), bumpScale: maps.bumpScale,
    roughnessMap: texture(maps.roughness, THREE.NoColorSpace),
    side: THREE.FrontSide,
  });
  material.name = `procedural-${maps.kind}`;
  material.userData = { provenance: 'procedural_pattern', photographic: false, tileMeters: maps.tileMeters };
  return material;
}
