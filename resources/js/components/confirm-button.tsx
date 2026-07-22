import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

interface ConfirmButtonProps {
    onConfirm: () => void;
    title: string;
    description?: React.ReactNode;
    confirmLabel?: string;
    triggerLabel: React.ReactNode;
    disabled?: boolean;
    className?: string;
}

/**
 * A button that requires an explicit Proceed/Cancel confirmation before firing
 * its action (used before any migration file is written).
 */
export function ConfirmButton({
    onConfirm,
    title,
    description,
    confirmLabel = 'Proceed',
    triggerLabel,
    disabled,
    className,
}: ConfirmButtonProps) {
    const [open, setOpen] = useState(false);

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button type="button" disabled={disabled} className={className}>
                    {triggerLabel}
                </Button>
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>{title}</DialogTitle>
                    {description && (
                        <DialogDescription asChild>
                            <div className="break-words">{description}</div>
                        </DialogDescription>
                    )}
                </DialogHeader>
                <DialogFooter>
                    <DialogClose asChild>
                        <Button variant="outline">Cancel</Button>
                    </DialogClose>
                    <Button
                        onClick={() => {
                            setOpen(false);
                            onConfirm();
                        }}
                    >
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
