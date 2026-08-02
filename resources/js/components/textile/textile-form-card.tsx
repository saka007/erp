import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';

interface TextileFormCardProps {
    title: string;
    icon?: LucideIcon;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
}

export function TextileFormCard({ title, icon: Icon, children, className, contentClassName = 'p-5 space-y-4' }: TextileFormCardProps) {
    return (
        <Card className={className}>
            <CardContent className={contentClassName}>
                <div className="flex items-center gap-2">
                    {Icon ? <Icon className="h-5 w-5 text-primary" /> : null}
                    <h2 className="font-semibold">{title}</h2>
                </div>
                {children}
            </CardContent>
        </Card>
    );
}
