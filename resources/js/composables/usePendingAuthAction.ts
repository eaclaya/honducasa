import { useHttp } from '@inertiajs/vue3';
import { store as storePendingAction } from '@/routes/auth/pending-action';

type SavedSearchPayload = {
    name: string;
    filters: Record<string, string | number | boolean | undefined>;
    alerts_enabled: boolean;
};

export type PendingAuthAction =
    | {
          type: 'favorite_property';
          payload: { property_slug: string };
      }
    | {
          type: 'save_search';
          payload: { saved_search: SavedSearchPayload };
      }
    | {
          type: 'start_conversation';
          payload: { property_slug: string; body: string };
      };

type PendingAuthActionRequest = {
    type: PendingAuthAction['type'];
    payload: PendingAuthAction['payload'];
    redirect: string;
};

export const usePendingAuthAction = () => {
    const request = useHttp<PendingAuthActionRequest>({
        type: 'favorite_property',
        payload: { property_slug: '' },
        redirect: '/',
    });

    const remember = async (
        action: PendingAuthAction,
        redirect: string,
    ): Promise<boolean> => {
        request.type = action.type;
        request.payload = action.payload;
        request.redirect = redirect;

        try {
            await request.submit(storePendingAction());

            return true;
        } catch {
            return false;
        }
    };

    return {
        remember,
    };
};
