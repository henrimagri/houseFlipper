import PropertyMaquetteViewer from '@/components/PropertyMaquetteViewer';

type Props = {
  title: string;
  areaM2?: number | null;
  bedrooms?: number | null;
  bathrooms?: number | null;
  parkingSpaces?: number | null;
  propertyType?: string | null;
  notes?: string | null;
  className?: string;
};

export default function ListingLayoutViewer(props: Props) {
  return <PropertyMaquetteViewer {...props} showChrome className={props.className ?? 'w-full h-[420px] rounded-2xl'} />;
}
