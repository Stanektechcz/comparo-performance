import { Link } from '@inertiajs/react';
import { buttonStyles } from '@/components/comparo/button-styles';
import { useSharedProps } from '@/hooks/use-shared-props';
import { accountUrls } from '@/lib/catalog-urls';
import { cn } from '@/lib/utils';

type AccountLinksProps = {
    className?: string;
    onNavigate?: () => void;
};

export function AccountLinks({ className, onNavigate }: AccountLinksProps) {
    const { auth } = useSharedProps();

    if (auth.user) {
        return (
            <div className={cn('flex items-center gap-2', className)}>
                <Link
                    href={accountUrls.dashboard()}
                    onClick={onNavigate}
                    className={cn(buttonStyles.secondary, 'min-h-10')}
                >
                    <span className="sr-only">Your account: </span>
                    <span className="max-w-40 truncate">{auth.user.name}</span>
                </Link>
            </div>
        );
    }

    return (
        <div className={cn('flex items-center gap-2', className)}>
            <Link
                href={accountUrls.login()}
                onClick={onNavigate}
                className={cn(buttonStyles.secondary, 'min-h-10')}
            >
                Log in
            </Link>
            <Link
                href={accountUrls.register()}
                onClick={onNavigate}
                className={cn(buttonStyles.primary, 'min-h-10')}
            >
                Register
            </Link>
        </div>
    );
}
