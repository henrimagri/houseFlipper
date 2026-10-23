import { Outlet } from 'react-router-dom';
import SiteSurface from './SiteSurface';

export default function PublicLayout() {
  return <SiteSurface><main id="inicio-conteudo" className="fc-public-main"><Outlet /></main></SiteSurface>;
}
