/**
 * External dependencies
 */
import type { ReactNode } from 'react';

type NoticeKind = 'success' | 'error' | 'warning' | 'info';

export interface NoticeState {
	type: NoticeKind;
	message: ReactNode;
}
