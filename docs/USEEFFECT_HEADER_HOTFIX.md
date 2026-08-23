# Header useEffect Hotfix

Fixed runtime crash:

`Uncaught ReferenceError: useEffect is not defined`

Cause:
HeaderNavigation started using `useEffect` for the Luna-chat manual navigation open signal, but `GenerateHeader.jsx` imported only `useState`.

Fix:
`import React, { useEffect, useState } from "react";`

No behavior changes beyond restoring the Builder runtime.
