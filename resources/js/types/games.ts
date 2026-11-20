// Props sent by GameController@index. Shapes mirror the PHP value objects:
// App\Services\Library\LibraryGame and App\Services\Library\DailyStatus.

export type Game = {
    /** The Essense API's game ID. */
    id: number;
    name: string;
    votes: number;
    votedByMe: boolean;
};

export type ActionType = 'vote' | 'add';

export type RefundReason = 'withdrawn' | 'game_removed';

export type DailyStatus = {
    canAct: boolean;
    usedFor: ActionType | null;
    gameName: string | null;
    refundReason: RefundReason | null;
    /** ISO 8601: the next midnight in the user's timezone. */
    resetsAt: string;
};

export type GamesPageProps = {
    /** Null when the API couldn't be reached; see loadError. */
    games: Game[] | null;
    status: DailyStatus;
    loadError: string | null;
};
