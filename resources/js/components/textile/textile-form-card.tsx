import { LucideIcon } from 'lucide-react';
import { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';

interface TextileFormCardProps {
    title?: string;
    icon?: LucideIcon;
    children: ReactNode;
    className?: string;
    contentClassName?: string;
    /** Hide the header row — used when the card is embedded in a workflow step card that already shows the title. */
    showHeader?: boolean;
}

export function TextileFormCard({ title, icon: Icon, children, className, contentClassName = 'p-5 space-y-4', showHeader = true }: TextileFormCardProps) {
    return (
        <Card className={className}>
            <CardContent className={contentClassName}>
                {showHeader ? (
                    <div className="flex items-center gap-2">
                        {Icon ? <Icon className="h-5 w-5 text-primary" /> : null}
                        <h2 className="font-semibold">{title}</h2>
                    </div>
                ) : null}
                {children}
            </CardContent>
        </Card>
    );
}
