/**
 * Encodes a drawn search area for the `polygon` query parameter as
 * `lng,lat;lng,lat;...`. Inertia's GET query serialization flattens an
 * array of coordinate pairs into one indistinguishable flat list, so a
 * nested array isn't reliable here — a delimited string sidesteps that.
 */
export const encodePolygon = (ring: Array<[number, number]>): string =>
    ring.map(([lng, lat]) => `${lng},${lat}`).join(';');
