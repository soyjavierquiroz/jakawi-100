import { createContext, useContext } from 'react';

export const LandingAction = createContext<() => void>(() => {});
export const useLandingAction = () => useContext(LandingAction);
