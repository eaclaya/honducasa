type SwipeGestureOptions = {
    onSwipeLeft: () => void;
    onSwipeRight: () => void;
    threshold?: number;
};

type SwipeGesture = {
    onPointerDown: (event: PointerEvent) => void;
    onPointerUp: (event: PointerEvent) => void;
    onPointerCancel: (event: PointerEvent) => void;
    shouldSuppressClick: () => boolean;
};

export const createSwipeGesture = ({
    onSwipeLeft,
    onSwipeRight,
    threshold = 48,
}: SwipeGestureOptions): SwipeGesture => {
    let activePointerId: number | null = null;
    let startX = 0;
    let startY = 0;
    let suppressClick = false;

    const reset = (): void => {
        activePointerId = null;
    };

    const onPointerDown = (event: PointerEvent): void => {
        if (
            !event.isPrimary ||
            (event.pointerType === 'mouse' && event.button !== 0) ||
            (event.target as Element | null)?.closest('button')
        ) {
            return;
        }

        activePointerId = event.pointerId;
        startX = event.clientX;
        startY = event.clientY;

        if (event.currentTarget instanceof HTMLElement) {
            event.currentTarget.setPointerCapture(event.pointerId);
        }
    };

    const onPointerUp = (event: PointerEvent): void => {
        if (activePointerId !== event.pointerId) {
            return;
        }

        const distanceX = event.clientX - startX;
        const distanceY = event.clientY - startY;
        const isHorizontalSwipe =
            Math.abs(distanceX) >= threshold &&
            Math.abs(distanceX) > Math.abs(distanceY) * 1.25;

        reset();

        if (!isHorizontalSwipe) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        suppressClick = true;

        if (distanceX < 0) {
            onSwipeLeft();
        } else {
            onSwipeRight();
        }

        window.setTimeout(() => {
            suppressClick = false;
        });
    };

    const onPointerCancel = (event: PointerEvent): void => {
        if (activePointerId === event.pointerId) {
            reset();
        }
    };

    return {
        onPointerDown,
        onPointerUp,
        onPointerCancel,
        shouldSuppressClick: () => suppressClick,
    };
};
