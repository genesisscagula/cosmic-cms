import { logoFilterFor } from './logoFilters';
export default function ThemeLogo({ theme='midnight', className='h-12 w-auto', alt='Your Logo' }) {
  return <img src="/storage/branding/your-logo.png" alt={alt} className={className} style={{ filter: logoFilterFor(theme) }} />;
}
