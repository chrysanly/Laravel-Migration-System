import { ChevronUp, ChevronDown } from 'lucide-react';
import { Button } from '@/components/ui/button';

/**
 * Fixed up/down buttons for quickly jumping to the top or bottom of long pages
 * (e.g. the table overview). Scrolls the window smoothly.
 */
export function ScrollButtons() {
    return (
        <div className="fixed bottom-6 end-6 z-50 flex flex-col gap-2">
            <Button
                type="button"
                size="icon"
                variant="secondary"
                className="rounded-full shadow-md"
                aria-label="Scroll to top"
                onClick={() => window.scrollTo({ top: 0, behavior: 'smooth' })}
            >
                <ChevronUp className="size-5" />
            </Button>
            <Button
                type="button"
                size="icon"
                variant="secondary"
                className="rounded-full shadow-md"
                aria-label="Scroll to bottom"
                onClick={() =>
                    window.scrollTo({ top: document.documentElement.scrollHeight, behavior: 'smooth' })
                }
            >
                <ChevronDown className="size-5" />
            </Button>
        </div>
    );
}
