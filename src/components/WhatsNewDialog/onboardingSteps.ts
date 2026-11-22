import { t } from '@nextcloud/l10n'

import type { OnboardingStep } from '@/utils/whatsNew'

/**
 * Steps shown once to people updating past their version, for changes the
 * screen alone does not reveal. Each one passes the onboarding rubric in the
 * release-notes skill and is approved by the owner before it is added.
 */
export function onboardingSteps(): OnboardingStep[] {
  return [
    {
      version: '0.40.0',
      title: t('pantry', 'Type the amount with the item'),
      body: t(
        'pantry',
        'Type "2 L milk" and Pantry fills in the amount. A category chip appears from what your household picked before, and dropping the chip removes the guess. Turn it off with "Smart input" in Settings.',
      ),
    },
  ]
}
