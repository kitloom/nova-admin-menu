// The class strings Nova's own buttons are built from.
//
// Nova draws them with laravel-nova-ui, but that package resolves an icon by
// `import *` over all four heroicon sets, so importing it costs the bundle
// 800KB whether one icon is used or three hundred. These are the strings it
// composes for its variants, and Nova's stylesheet already carries every one
// of them - so the buttons here are the buttons Nova draws, at no cost.
export const BUTTON =
  'border text-left appearance-none cursor-pointer rounded text-sm font-bold ' +
  'inline-flex items-center justify-center ' +
  'focus:outline-none focus:ring ring-primary-200 dark:ring-gray-600 ' +
  'disabled:cursor-not-allowed disabled:opacity-50';

// laravel-nova-ui's two sizes. They are separate constants rather than a
// default in BUTTON because these utilities all carry the same specificity:
// which one wins is decided by the order Nova's stylesheet happens to define
// them in, not by the order they are listed on the element.
export const SMALL = 'h-7 px-2';
export const LARGE = 'h-9 px-3';

export const NEUTRAL =
  'bg-transparent border-transparent ' +
  'text-gray-600 dark:text-gray-400 ' +
  'hover:[&:not(:disabled)]:bg-gray-700/5 dark:hover:[&:not(:disabled)]:bg-gray-950';

export const DANGER =
  'bg-transparent border-transparent ' +
  'text-red-500 ' +
  'hover:[&:not(:disabled)]:bg-gray-700/5 dark:hover:[&:not(:disabled)]:bg-gray-950';

export const SOLID =
  'shadow bg-primary-500 border-primary-500 text-white dark:text-gray-900 ' +
  'hover:[&:not(:disabled)]:bg-primary-400 hover:[&:not(:disabled)]:border-primary-400';

export const SOLID_DANGER =
  'shadow bg-red-500 border-red-500 text-white dark:text-red-950 ' +
  'hover:[&:not(:disabled)]:bg-red-400 hover:[&:not(:disabled)]:border-red-400';

// A ghost button whose panel is open, so the row says which one it is.
export const ACTIVE = 'border-transparent text-primary-500 bg-gray-700/5 dark:bg-gray-950';

export default { BUTTON, LARGE, NEUTRAL, DANGER, SOLID, SOLID_DANGER, ACTIVE };
