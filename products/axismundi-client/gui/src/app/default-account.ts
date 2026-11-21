// Which JMAP Account the app opens with. Angular-free so `node --test` can check it.

export interface AccountChoice {id: string; isPersonal: boolean}

/**
 * The server's primary Account for the capability first (Session.primaryAccounts), then a personal
 * one, then any. A primary that names an Account this login cannot see is ignored.
 */
export function defaultAccount(accounts: AccountChoice[], primary: string | undefined): string | null {
  return (accounts.find(account => account.id === primary)
    ?? accounts.find(account => account.isPersonal) ?? accounts[0])?.id ?? null;
}
