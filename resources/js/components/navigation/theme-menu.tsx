import { Monitor, Moon, Sun } from 'lucide-react';
import { buttonStyles } from '@/components/comparo/button-styles';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { Appearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';

const options: { value: Appearance; label: string }[] = [
    { value: 'light', label: 'Light' },
    { value: 'dark', label: 'Dark' },
    { value: 'system', label: 'System' },
];

function isAppearance(value: string): value is Appearance {
    return options.some((option) => option.value === value);
}

/** Light / dark / system theme picker (persists via use-appearance). */
export function ThemeMenu() {
    const { appearance, resolvedAppearance, updateAppearance } =
        useAppearance();
    const current =
        options.find((option) => option.value === appearance)?.label ??
        'System';
    const Icon =
        appearance === 'system'
            ? Monitor
            : resolvedAppearance === 'dark'
              ? Moon
              : Sun;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger
                className={buttonStyles.icon}
                aria-label={`Colour theme: ${current}`}
                title={`Colour theme: ${current}`}
            >
                <Icon aria-hidden="true" className="size-4" />
            </DropdownMenuTrigger>
            <DropdownMenuContent
                align="end"
                className="min-w-40 rounded-card border-line-2 bg-surface shadow-pop"
            >
                <DropdownMenuLabel className="eyebrow">Theme</DropdownMenuLabel>
                <DropdownMenuRadioGroup
                    value={appearance}
                    onValueChange={(value) => {
                        if (isAppearance(value)) {
                            updateAppearance(value);
                        }
                    }}
                >
                    {options.map((option) => (
                        <DropdownMenuRadioItem
                            key={option.value}
                            value={option.value}
                            className="min-h-10"
                        >
                            {option.label}
                        </DropdownMenuRadioItem>
                    ))}
                </DropdownMenuRadioGroup>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
