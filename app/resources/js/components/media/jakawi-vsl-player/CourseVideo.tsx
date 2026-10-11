import { JakawiVslPlayer } from './JakawiVslPlayer';
import type { JakawiVslPlayerProps } from './types';

/** Pass only a server-authorized source. This wrapper does not grant access. */
export function CourseVideo(props: Omit<JakawiVslPlayerProps, 'mode'>) {
    return <JakawiVslPlayer {...props} mode="course" />;
}
