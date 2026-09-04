export type AreaUnit = 'm2' | 'vara2';

export const SQUARE_METERS_PER_SQUARE_VARA = 0.6972;

const roundForDisplay = (value: number): number =>
    Math.round((value + Number.EPSILON) * 100) / 100;

export const squareMetersToArea = (
    squareMeters: number,
    unit: AreaUnit,
): number =>
    roundForDisplay(
        unit === 'vara2'
            ? squareMeters / SQUARE_METERS_PER_SQUARE_VARA
            : squareMeters,
    );

export const areaToSquareMeters = (value: number, unit: AreaUnit): number =>
    Math.round(
        unit === 'vara2' ? value * SQUARE_METERS_PER_SQUARE_VARA : value,
    );
