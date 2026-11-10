import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 34"
            fill="currentColor"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <path d="M16 2 28 14 22 26H17V17.83a3 3 0 1 0-2 0V26h-5L4 14 16 2Zm-5 27h10a1.5 1.5 0 0 1 0 3H11a1.5 1.5 0 0 1 0-3Z" />
        </svg>
    );
}
