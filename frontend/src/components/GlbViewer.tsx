import { useEffect, useRef } from 'react';
import { getAuthToken } from '@/lib/authToken';

type Props = { src: string; className?: string };

export default function GlbViewer({ src, className }: Props) {
  const canvasRef = useRef<HTMLCanvasElement>(null);

  useEffect(() => {
    const canvas = canvasRef.current;
    if (!canvas) return;
    let disposed = false;
    let renderer: any;
    let frame = 0;
    const token = getAuthToken();

    (async () => {
      const THREE = await import('three');
      const { GLTFLoader } = await import('three/examples/jsm/loaders/GLTFLoader.js');
      const { OrbitControls } = await import('three/examples/jsm/controls/OrbitControls.js');
      if (disposed) return;

      const scene = new THREE.Scene();
      scene.background = new THREE.Color(0x0f172a);
      const camera = new THREE.PerspectiveCamera(50, canvas.clientWidth / Math.max(canvas.clientHeight, 1), 0.1, 200);
      camera.position.set(3.2, 2.2, 4.2);
      renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: false });
      renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
      renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);
      scene.add(new THREE.AmbientLight(0xffffff, 0.85));
      const sun = new THREE.DirectionalLight(0xffffff, 1.1);
      sun.position.set(4, 8, 6);
      scene.add(sun);
      const controls = new OrbitControls(camera, canvas);
      controls.enableDamping = true;
      const loader = new GLTFLoader();
      const buffer = await fetch(src, { headers: token ? { Authorization: `Bearer ${token}` } : {} }).then(response => {
        if (!response.ok) throw new Error('model');
        return response.arrayBuffer();
      });
      if (disposed) return;
      loader.parse(buffer, '', (gltf: any) => {
        if (disposed) return;
        scene.add(gltf.scene);
        const box = new THREE.Box3().setFromObject(gltf.scene);
        const size = box.getSize(new THREE.Vector3()).length();
        const center = box.getCenter(new THREE.Vector3());
        controls.target.copy(center);
        camera.position.copy(center).add(new THREE.Vector3(size * 0.4, size * 0.3, size * 0.5));
        camera.near = size / 100;
        camera.far = size * 20;
        camera.updateProjectionMatrix();
      });
      const tick = () => {
        if (disposed) return;
        controls.update();
        renderer.render(scene, camera);
        frame = requestAnimationFrame(tick);
      };
      tick();
      const onResize = () => {
        if (!canvas || disposed) return;
        camera.aspect = canvas.clientWidth / Math.max(canvas.clientHeight, 1);
        camera.updateProjectionMatrix();
        renderer.setSize(canvas.clientWidth, canvas.clientHeight, false);
      };
      window.addEventListener('resize', onResize);
    })().catch(() => undefined);

    return () => {
      disposed = true;
      cancelAnimationFrame(frame);
      renderer?.dispose?.();
    };
  }, [src]);

  return <canvas ref={canvasRef} className={className} role="img" aria-label="Visualizador 3D do imóvel" />;
}
