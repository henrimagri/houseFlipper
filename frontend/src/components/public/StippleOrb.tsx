import { useEffect, useRef, useState } from 'react';

/** Decorative point sphere. No map, customer locations, or reconstruction data. */
export default function StippleOrb({ dark, horizon = false }: { dark: boolean; horizon?: boolean }) {
  const canvas = useRef<HTMLCanvasElement>(null);
  const rotation = useRef({ x: -.18, y: .35 });
  const pointer = useRef<{ x: number; y: number; id: number; originX: number; originY: number } | null>(null);
  const velocity = useRef({ x: 0, y: 0 });
  const hover = useRef({ x: 0, y: 0 });
  const waves = useRef<{ x: number; y: number; time: number }[]>([]);
  const [touched, setTouched] = useState(false);

  useEffect(() => {
    const element = canvas.current;
    if (!element) return;
    const host = element.parentElement!;
    const ctx = element.getContext('2d');
    if (!ctx) return;
    const media = window.matchMedia('(prefers-reduced-motion: reduce)');
    let width = 0, height = 0, frame = 0, last = 0, visible = true, disposed = false;
    const count = horizon ? 4400 : 7600;
    const points = Array.from({ length: count }, (_, i) => {
      const y = 1 - 2 * (i + .5) / count;
      const angle = i * Math.PI * (3 - Math.sqrt(5));
      const radius = Math.sqrt(1 - y * y);
      const x = Math.cos(angle) * radius, z = Math.sin(angle) * radius;
      const band = Math.sin(y * 9 + x * 4 + z * 2.5) + .48 * Math.cos(z * 7 - x * 5);
      return { x, y, z, band };
    });
    function draw(time: number) {
      frame = 0;
      if (disposed || !ctx || !element) return;
      if (!media.matches && last && time - last < 32) {
        if (visible && !document.hidden) frame = requestAnimationFrame(draw);
        return;
      }
      const delta = last ? Math.min((time - last) / 1000, .05) : 0;
      last = time;
      if (!media.matches && !pointer.current) {
        rotation.current.y += delta * .16 + velocity.current.y;
        rotation.current.x = Math.max(-1.1, Math.min(1.1, rotation.current.x + velocity.current.x));
        velocity.current.x *= .93; velocity.current.y *= .93;
      }
      const motionTime = media.matches ? 0 : time / 1000;
      waves.current = waves.current.filter(wave => time - wave.time < 2200);
      ctx.clearRect(0, 0, width, height);
      const r = (horizon ? width * .45 : Math.min(width, height) * .415) * (1 + .014 * Math.sin(motionTime * .9));
      const cx = width / 2, cy = horizon ? height + r * .70 : height * .48;
      const { x: rx, y: ry } = rotation.current;
      const sx = Math.sin(rx), cxr = Math.cos(rx), sy = Math.sin(ry), cyr = Math.cos(ry);
      // Draw back to front without allocating/sorting point objects each frame.
      for (let pass = 0; pass < 2; pass++) {
        for (let i = 0; i < points.length; i += width < 500 ? 2 : 1) {
          const p = points[i];
          const x = p.x * cyr + p.z * sy, z0 = p.z * cyr - p.x * sy;
          const y = p.y * cxr - z0 * sx, z = z0 * cxr + p.y * sx;
          if ((z > 0 ? 1 : 0) !== pass) continue;
          const depth = (z + 1) / 2;
          let ripple = 0;
          if (!media.matches) for (const wave of waves.current) {
            const age = (time - wave.time) / 1000;
            const distance = Math.hypot(x - wave.x, y - wave.y);
            const envelope = Math.exp(-Math.pow((distance - age * 1.1) * 5, 2)) * Math.max(0, 1 - age / 2.2);
            ripple += Math.sin(distance * 20 - age * 18) * envelope;
          }
          const relief = 1 + .035 * Math.sin(p.band * 3 + motionTime * .75) + ripple * .065;
          const perspective = 3 / (3 - z * .28);
          const px = cx + x * r * relief * perspective, py = cy + y * r * relief * perspective;
          if (py < -2 || py > height + 2) continue;
          const teal = Math.sin(p.y * 9 + p.x * 4 + p.z * 2.5 + motionTime * .22) + .48 * Math.cos(p.z * 7 - p.x * 5) > -.35;
          ctx.fillStyle = teal ? (dark ? '#54b9a8' : '#187f74') : (dark ? '#c5c4af' : '#787b67');
          const light = Math.max(0, x * hover.current.x + y * hover.current.y) * .16;
          ctx.globalAlpha = Math.min(1, (dark ? .10 : .08) + depth * (teal ? .77 : .50) + Math.abs(ripple) * .5 + light);
          const size = (horizon ? .65 : .55) + depth * (width < 500 ? .68 : .86) + Math.abs(ripple) * .8;
          ctx.beginPath(); ctx.arc(px, py, size, 0, Math.PI * 2); ctx.fill();
        }
      }
      ctx.globalAlpha = 1;
      if (!media.matches && visible && !document.hidden) frame = requestAnimationFrame(draw);
    }
    const start = () => {
      if (frame) cancelAnimationFrame(frame);
      last = 0;
      if (visible && !document.hidden) frame = requestAnimationFrame(draw);
    };
    const resize = new ResizeObserver(() => {
      const bounds = host.getBoundingClientRect();
      width = bounds.width; height = bounds.height;
      const dpr = Math.min(window.devicePixelRatio || 1, 2);
      element.width = Math.round(width * dpr); element.height = Math.round(height * dpr);
      element.style.width = `${width}px`; element.style.height = `${height}px`;
      ctx.setTransform(dpr, 0, 0, dpr, 0, 0); start();
    });
    const observer = new IntersectionObserver(([entry]) => { visible = entry.isIntersecting; start(); });
    resize.observe(host); observer.observe(host);
    document.addEventListener('visibilitychange', start);
    media.addEventListener('change', start);
    host.addEventListener('stipple-redraw', start);
    return () => {
      disposed = true; cancelAnimationFrame(frame); resize.disconnect(); observer.disconnect();
      document.removeEventListener('visibilitychange', start); media.removeEventListener('change', start); host.removeEventListener('stipple-redraw', start);
    };
  }, [dark, horizon]);

  const redraw = () => canvas.current?.parentElement?.dispatchEvent(new Event('stipple-redraw'));
  const ripple = (element: HTMLElement, clientX?: number, clientY?: number) => {
    const bounds = element.getBoundingClientRect();
    const radius = Math.min(bounds.width, bounds.height) * .415;
    waves.current = [...waves.current.slice(-3), { x: clientX == null ? 0 : (clientX - bounds.left - bounds.width / 2) / radius, y: clientY == null ? 0 : (clientY - bounds.top - bounds.height * .48) / radius, time: performance.now() }];
    setTouched(true); redraw();
  };
  const stop = () => { pointer.current = null; };
  return <div className={horizon ? 'landing-horizon' : 'landing-orb'}
    {...(!horizon ? { role: 'group', tabIndex: 0, 'aria-label': 'Esfera de partículas interativa. Arraste ou use as setas para girar. Clique ou pressione Enter para criar uma onda.' } : { 'aria-hidden': true as const })}
    data-touched={touched ? '' : undefined}
    onKeyDown={event => {
      if (horizon) return;
      if (event.key === 'Enter' || event.key === ' ') { event.preventDefault(); ripple(event.currentTarget); return; }
      if (!['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown'].includes(event.key)) return;
      event.preventDefault(); setTouched(true);
      rotation.current.y += event.key === 'ArrowLeft' ? -.12 : event.key === 'ArrowRight' ? .12 : 0;
      rotation.current.x += event.key === 'ArrowUp' ? -.12 : event.key === 'ArrowDown' ? .12 : 0; redraw();
    }}
    onPointerDown={event => {
      if (horizon) return;
      if (event.button !== 0) return;
      velocity.current = { x: 0, y: 0 };
      pointer.current = { x: event.clientX, y: event.clientY, id: event.pointerId, originX: event.clientX, originY: event.clientY };
      event.currentTarget.setPointerCapture(event.pointerId); setTouched(true);
    }}
    onPointerMove={event => {
      const bounds = event.currentTarget.getBoundingClientRect();
      hover.current = { x: (event.clientX - bounds.left) / bounds.width * 2 - 1, y: (event.clientY - bounds.top) / bounds.height * 2 - 1 };
      const previous = pointer.current;
      if (!previous || previous.id !== event.pointerId) return;
      const dx = (event.clientX - previous.x) * .006, dy = (event.clientY - previous.y) * .006;
      rotation.current.y += dx;
      rotation.current.x = Math.max(-1.1, Math.min(1.1, rotation.current.x + dy));
      velocity.current = { x: Math.max(-.06, Math.min(.06, dy)), y: Math.max(-.06, Math.min(.06, dx)) };
      pointer.current = { ...previous, x: event.clientX, y: event.clientY }; redraw();
    }} onPointerUp={event => {
      const previous = pointer.current;
      if (previous && Math.hypot(event.clientX - previous.originX, event.clientY - previous.originY) < 7) ripple(event.currentTarget, event.clientX, event.clientY);
      stop();
    }} onPointerLeave={() => { hover.current = { x: 0, y: 0 }; }} onPointerCancel={stop} onLostPointerCapture={stop}>
    <canvas ref={canvas} aria-hidden="true" />
    {!horizon && <span className="landing-orb-hint landing-mono"><i />Arraste para explorar</span>}
  </div>;
}
