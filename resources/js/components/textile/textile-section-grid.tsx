import { ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface TextileSectionGridProps {
    children: ReactNode;
    className?: string;
}

export function TextileSectionGrid({ children, className }: TextileSectionGridProps) {
    return <div className={cn('mt-6 grid gap-6', className)}>{children}</div>;
}
