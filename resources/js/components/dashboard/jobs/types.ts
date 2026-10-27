export type HorizonStatus = 'running' | 'paused' | 'inactive';

export type HorizonCommand = 'restart' | 'pause' | 'continue';

export interface QueueWorkload {
    name: string;
    length: number;
    /** Seconds until the queue is clear at the current pace. */
    wait: number;
    processes: number;
}

export interface Supervisor {
    name: string;
    status: string;
    queues: string[];
    processes: number;
    maxProcesses: number | null;
}

export interface FailedJob {
    id: string;
    name: string;
    queue: string;
    failedAt: string | null;
    exception: string;
    summary: string;
    retried: boolean;
}

export interface RecentJob {
    id: string;
    name: string;
    queue: string;
    status: string;
    queuedAt: string | null;
    completedAt: string | null;
    /** Seconds from reserved to finished. */
    runtime: number | null;
}

/** Mirrors HorizonJobsReport::execute(). */
export interface JobsReport {
    status: HorizonStatus;
    stats: {
        jobsPerMinute: number;
        processes: number;
        recentJobs: number;
        failedJobs: number;
        pendingJobs: number;
    };
    /** How long Horizon keeps recent and failed jobs, in minutes. */
    periods: { recent: number; failed: number };
    workload: QueueWorkload[];
    supervisors: Supervisor[];
    failedJobs: FailedJob[];
    recentJobs: RecentJob[];
}

/** Mirrors HorizonPayload::resolve(). */
export type HorizonPayload =
    { state: 'ready'; data: JobsReport } | { state: 'unavailable' };
