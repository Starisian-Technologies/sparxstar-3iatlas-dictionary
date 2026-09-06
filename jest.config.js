/**
 * Jest configuration.
 *
 * `transform` was previously empty on purpose — the test files are CommonJS and
 * one of them uses a module-level `return` as a skip guard, which babel rejects.
 * That still holds, so the transform is scoped to `src/js` only: modules under
 * `src/js` are ES modules while `package.json` declares `"type": "commonjs"`,
 * so babel (see `babel.config.json`) has to compile one before a test can
 * require it. Test files themselves remain untransformed.
 */
module.exports = {
    testEnvironment: 'node',
    transform: {
        '/src/js/.+\\.[jt]sx?$': 'babel-jest',
    },
    testPathIgnorePatterns: [
        '/node_modules/',
        '/vendor/',
        '/build/',
        '/build_test/',
        '/dest_test/',
    ],
};
