export type GoogleCredentialResponse = {
  credential?: string;
};

export type GsiButtonConfiguration = {
  type?: 'standard' | 'icon';
  theme?: 'outline' | 'filled_blue' | 'filled_black';
  size?: 'large' | 'medium' | 'small';
  shape?: 'rectangular' | 'pill' | 'circle' | 'square';
  text?: 'signin_with' | 'signup_with' | 'continue_with' | 'signin';
  logo_alignment?: 'left' | 'center';
  width?: string | number;
};

export interface GoogleIdentity {
  accounts: {
    id: {
      initialize(options: { client_id: string; callback: (response: GoogleCredentialResponse) => void }): void;
      renderButton(element: HTMLElement, options: GsiButtonConfiguration | Record<string, unknown>): void;
      renderOption?(element: HTMLElement, options: GsiButtonConfiguration | Record<string, unknown>): void;
      prompt(): void;
    };
  };
}

declare global {
  interface Window {
    google?: GoogleIdentity;
  }
}

export {};
