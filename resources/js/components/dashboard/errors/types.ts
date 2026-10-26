/** Mirrors App\Dashboard\SentryPayload::resolve(). */
export type SentryPayload<T> =
    | { state: 'ready'; data: T }
    | { state: 'unconfigured' | 'missing' | 'unavailable' };

export type SentryLevel = 'fatal' | 'error' | 'warning' | 'info' | 'debug';

export interface Comparison<T> {
    value: T;
    previous: T;
}

/** Mirrors SentryErrorsReport::execute(). */
export interface ErrorsReport {
    summary: {
        errors: Comparison<number>;
        dropped: Comparison<number> & { breakdown: Record<string, number> };
        usersAffected: { value: number; exact: boolean };
        unresolved: number;
        newIssues: number;
    };
    volume: {
        interval: '1h' | '1d';
        labels: string[];
        accepted: number[];
        dropped: number[];
    };
    quota: {
        used: number;
        limit: number;
        percent: number;
        periodStart: string;
        resetsAt: string;
        daysUntilReset: number;
    };
    environments: string[];
}

/** Mirrors SentryIssues::row(). */
export interface IssueRow {
    id: string;
    shortId: string;
    title: string;
    culprit: string;
    type: string | null;
    level: SentryLevel;
    status: string;
    substatus: string | null;
    isUnhandled: boolean;
    events: number;
    users: number;
    firstSeen: string | null;
    lastSeen: string | null;
    trend: number[];
    permalink: string | null;
}

/** Mirrors SentryIssues::execute(). */
export interface IssuePage {
    rows: IssueRow[];
    total: number;
    next: string | null;
    previous: string | null;
    query: string;
}

export interface StackFrame {
    filename: string;
    function: string | null;
    lineNo: number | null;
    colNo: number | null;
    inApp: boolean;
    context: { line: number; code: string }[];
}

/** Mirrors SentryIssueDetail::execute(). */
export interface IssueDetail {
    issue: {
        id: string;
        shortId: string;
        title: string;
        culprit: string;
        type: string | null;
        value: string | null;
        level: SentryLevel;
        status: string;
        substatus: string | null;
        isUnhandled: boolean;
        platform: string | null;
        events: number;
        users: number;
        firstSeen: string | null;
        lastSeen: string | null;
        firstRelease: string | null;
        lastRelease: string | null;
        permalink: string | null;
        trend: { labels: number[]; counts: number[] };
    };
    event: {
        id: string;
        dateCreated: string | null;
        message: string | null;
        environment: string | null;
        release: string | null;
        exceptions: {
            type: string;
            value: string;
            handled: boolean | null;
            mechanism: string | null;
            frames: StackFrame[];
        }[];
        breadcrumbs: {
            timestamp: string | null;
            category: string | null;
            level: string;
            type: string;
            message: string | null;
        }[];
        request: { method: string | null; url: string } | null;
        user: {
            id: string | null;
            email: string | null;
            username: string | null;
            ip: string | null;
        } | null;
        contexts: { key: string; value: string }[];
        tags: { key: string; value: string }[];
    } | null;
    tags: {
        key: string;
        name: string;
        total: number;
        top: { value: string; count: number; percent: number }[];
    }[];
}
