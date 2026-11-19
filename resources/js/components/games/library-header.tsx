import AppearanceToggleDropdown from '@/components/appearance-dropdown';
import { Avatar, AvatarFallback } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { UserMenuContent } from '@/components/user-menu-content';
import { useInitials } from '@/hooks/use-initials';
import { type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';

export function LibraryHeader() {
    const { auth } = usePage<SharedData>().props;
    const getInitials = useInitials();

    return (
        <header className="border-b">
            <div className="mx-auto flex h-14 max-w-2xl items-center justify-between gap-4 px-4 sm:px-6">
                <Link href={route('games.index')} className="text-brand-ink text-base font-semibold tracking-tight">
                    Office Game Library
                </Link>
                <div className="flex items-center gap-1">
                    <AppearanceToggleDropdown />
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" className="size-10 rounded-full p-1">
                                <Avatar className="size-8">
                                    <AvatarFallback className="bg-secondary text-secondary-foreground text-xs font-semibold">
                                        {getInitials(auth.user.name)}
                                    </AvatarFallback>
                                </Avatar>
                                <span className="sr-only">Account menu for {auth.user.name}</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="w-56" align="end">
                            <UserMenuContent user={auth.user} />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </header>
    );
}
