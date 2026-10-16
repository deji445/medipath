import { twiTranslations } from '@/i18n/twi';

const translatableAttributes = ['aria-label', 'placeholder', 'title'] as const;

let observer: MutationObserver | null = null;

function translate(value: string): string {
    const normalized = value.replace(/\s+/g, ' ').trim();

    if (normalized === '') return value;

    const translation = twiTranslations[normalized];

    if (!translation) return value;

    const leadingWhitespace = value.match(/^\s*/u)?.[0] ?? '';
    const trailingWhitespace = value.match(/\s*$/u)?.[0] ?? '';

    return `${leadingWhitespace}${translation}${trailingWhitespace}`;
}

function shouldSkip(element: Element | null): boolean {
    return Boolean(
        element?.closest('[data-localize="off"], script, style, code, pre'),
    );
}

function localizeTextNode(node: Text): void {
    if (shouldSkip(node.parentElement)) return;

    const translated = translate(node.data);

    if (translated !== node.data) node.data = translated;
}

function localizeElement(element: Element): void {
    if (shouldSkip(element)) return;

    for (const attribute of translatableAttributes) {
        const value = element.getAttribute(attribute);

        if (value) element.setAttribute(attribute, translate(value));
    }

    const walker = document.createTreeWalker(element, NodeFilter.SHOW_TEXT);
    let currentNode = walker.nextNode();

    while (currentNode) {
        localizeTextNode(currentNode as Text);
        currentNode = walker.nextNode();
    }
}

function localizeNode(node: Node): void {
    if (node instanceof Text) {
        localizeTextNode(node);
        return;
    }

    if (node instanceof Element) localizeElement(node);
}

export function initializePageLocalization(): void {
    observer?.disconnect();
    observer = null;

    if (document.documentElement.lang !== 'tw') return;

    const [pageTitle, ...titleSuffix] = document.title.split(' - ');
    document.title = [translate(pageTitle), ...titleSuffix].join(' - ');
    localizeElement(document.documentElement);

    observer = new MutationObserver((mutations) => {
        observer?.disconnect();

        for (const mutation of mutations) {
            if (mutation.type === 'characterData') {
                localizeNode(mutation.target);
            }

            for (const node of mutation.addedNodes) localizeNode(node);
        }

        observer?.observe(document.documentElement, {
            childList: true,
            subtree: true,
            characterData: true,
        });
    });

    observer.observe(document.documentElement, {
        childList: true,
        subtree: true,
        characterData: true,
    });
}
