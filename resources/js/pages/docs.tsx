import { Head } from '@inertiajs/react';

interface DocsProps {
    html: string;
}

export default function Docs({ html }: DocsProps) {
    return (
        <div className="flex h-full flex-1 flex-col p-4">
            <Head title="Documentation" />

            <article
                className={[
                    'mx-auto w-full max-w-3xl text-sm leading-relaxed',
                    '[&_h1]:mb-2 [&_h1]:text-2xl [&_h1]:font-bold',
                    '[&_h2]:mt-8 [&_h2]:mb-2 [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:border-b [&_h2]:pb-1',
                    '[&_h3]:mt-6 [&_h3]:mb-2 [&_h3]:text-base [&_h3]:font-semibold',
                    '[&_p]:my-3 [&_p]:text-muted-foreground',
                    '[&_ul]:my-3 [&_ul]:list-disc [&_ul]:ps-6 [&_ol]:my-3 [&_ol]:list-decimal [&_ol]:ps-6',
                    '[&_li]:my-1 [&_li]:text-muted-foreground',
                    '[&_a]:text-primary [&_a]:underline',
                    '[&_strong]:font-semibold [&_strong]:text-foreground',
                    '[&_code]:rounded [&_code]:bg-muted [&_code]:px-1.5 [&_code]:py-0.5 [&_code]:font-mono [&_code]:text-xs',
                    '[&_pre]:my-3 [&_pre]:overflow-x-auto [&_pre]:rounded-lg [&_pre]:bg-muted [&_pre]:p-3',
                    '[&_pre_code]:bg-transparent [&_pre_code]:p-0',
                    '[&_blockquote]:my-4 [&_blockquote]:border-s-4 [&_blockquote]:border-primary/40 [&_blockquote]:ps-4 [&_blockquote]:text-foreground',
                    '[&_hr]:my-8 [&_hr]:border-border',
                ].join(' ')}
                dangerouslySetInnerHTML={{ __html: html }}
            />
        </div>
    );
}
