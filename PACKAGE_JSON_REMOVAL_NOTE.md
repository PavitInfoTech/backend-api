This repository has been configured as API-only. Node tooling was removed: the `package.json` and `vite.config.js` files are no longer required for the API to function.

If you need to restore front-end tooling in the future, use the following steps:

1. Recreate `package.json` by running `npm init -y` and adding the required dependencies.
2. Add back `vite.config.js` and any Tailwind config.
3. Run `npm install` and `npm run build` to produce the `public/build` artifacts.

Note: `package-lock.json` can be removed to avoid confusion when Node is not used.
