import { api, toApiError, type ApiError } from '@/lib/api';
import type { ConstraintSubmission, Single } from '@/types/models';

export type SubmissionAction = 'submit' | 'accept' | 'return';

/**
 * POST submit / accept / return. Resolves with the new row, or the error. A 422 from submit
 * lists incomplete courses in error.errors.courses.
 */
export async function runSubmissionAction(
    studyProgramId: number,
    action: SubmissionAction,
    body: Record<string, unknown> = {},
): Promise<{ row: ConstraintSubmission } | { error: ApiError; courses: string[] }> {
    try {
        const response = await api.post<Single<ConstraintSubmission>>(`constraint-submissions/${studyProgramId}/${action}`, body);

        return { row: response.data.data };
    } catch (e) {
        const error = toApiError(e);
        const courses = Array.isArray(error.errors.courses) ? error.errors.courses.map(String) : [];

        return { error, courses };
    }
}
