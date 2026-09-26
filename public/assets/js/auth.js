// AppiTutors Client-Side Firebase Authentication Handler

(function () {
    const config = window.APPITUTORS_FIREBASE_CONFIG || {};

    let firebaseApp = null;
    let firebaseAuth = null;
    let isConfigured = false;

    // Check if configuration is present and valid
    if (config.apiKey && config.apiKey !== 'AIzaSyDemoApiKey1234567890' && typeof firebase !== 'undefined') {
        try {
            firebaseApp = firebase.initializeApp(config);
            firebaseAuth = firebase.auth();
            isConfigured = true;
            console.log('[AppiTutors] Firebase initialized successfully.');
        } catch (e) {
            console.warn('[AppiTutors] Firebase initialization notice:', e.message);
        }
    } else {
        console.warn('[AppiTutors] Firebase client credentials not yet configured in .env or using placeholder.');
    }

    window.AppiAuth = {
        isConfigured: function () {
            return isConfigured && firebaseAuth !== null;
        },

        register: async function (email, password, firstName, lastName, role) {
            if (!this.isConfigured()) {
                console.warn('[AppiTutors] Registration attempted without live Firebase credentials configured.');
                throw new Error('Registration is temporarily unavailable. Please try again shortly.');
            }

            // 1. Create User in Firebase
            const userCredential = await firebaseAuth.createUserWithEmailAndPassword(email, password);
            const user = userCredential.user;

            // 2. Send Email Verification
            await user.sendEmailVerification();

            // 3. Update Firebase Display Name
            await user.updateProfile({
                displayName: `${firstName} ${lastName}`.trim()
            });

            // 4. Get ID Token
            const idToken = await user.getIdToken(true);

            // 5. Synchronize with PHP Backend Session
            const res = await fetch('/api/auth/session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.APPITUTORS_CSRF_TOKEN || ''
                },
                body: JSON.stringify({
                    idToken: idToken,
                    requestedRole: role,
                    firstName: firstName,
                    lastName: lastName
                })
            });

            const result = await res.json();
            if (!result.success) {
                throw new Error(result.message || 'Session synchronization failed');
            }

            return {
                user: user,
                backendData: result.data,
                needsVerification: true
            };
        },

        login: async function (email, password) {
            if (!this.isConfigured()) {
                throw new Error('Firebase Authentication is not yet configured with valid credentials in .env. Please update FIREBASE_API_KEY, FIREBASE_AUTH_DOMAIN, and FIREBASE_PROJECT_ID.');
            }

            // 1. Sign in with Firebase
            const userCredential = await firebaseAuth.signInWithEmailAndPassword(email, password);
            const user = userCredential.user;

            // 2. Check if email is verified
            if (!user.emailVerified) {
                // Allow user to trigger resend on verify-email page
                return {
                    user: user,
                    needsVerification: true
                };
            }

            // 3. Get ID Token
            const idToken = await user.getIdToken(true);

            // 4. Synchronize with PHP Backend
            const res = await fetch('/api/auth/session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.APPITUTORS_CSRF_TOKEN || ''
                },
                body: JSON.stringify({
                    idToken: idToken
                })
            });

            const result = await res.json();
            if (!result.success) {
                throw new Error(result.message || 'Login failed on backend');
            }

            return {
                user: user,
                backendData: result.data,
                needsVerification: false
            };
        },

        loginWithGoogle: async function () {
            if (!this.isConfigured()) {
                throw new Error('Google Sign-In is not currently available because Firebase OAuth is not configured in .env.');
            }

            const provider = new firebase.auth.GoogleAuthProvider();
            const result = await firebaseAuth.signInWithPopup(provider);
            const user = result.user;
            const idToken = await user.getIdToken(true);

            const res = await fetch('/api/auth/session.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': window.APPITUTORS_CSRF_TOKEN || ''
                },
                body: JSON.stringify({
                    idToken: idToken
                })
            });

            const backendResult = await res.json();
            if (!backendResult.success) {
                throw new Error(backendResult.message || 'Google login synchronization failed');
            }

            return {
                user: user,
                backendData: backendResult.data
            };
        },

        resendVerificationEmail: async function () {
            if (firebaseAuth && firebaseAuth.currentUser) {
                await firebaseAuth.currentUser.sendEmailVerification();
                return true;
            }
            throw new Error('No active Firebase user found to resend verification.');
        },

        logout: async function () {
            if (firebaseAuth) {
                try {
                    await firebaseAuth.signOut();
                } catch (e) {
                    console.warn('Firebase signout warning:', e);
                }
            }

            // Terminate backend session
            await fetch('/api/auth/logout.php', {
                method: 'POST'
            });

            window.location.href = '/login.php';
        }
    };
})();
