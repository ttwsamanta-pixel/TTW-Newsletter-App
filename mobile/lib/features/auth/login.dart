import 'package:flutter/material.dart';

import '../../core/auth.dart';
import '../../widgets/ui.dart';

class LoginScreen extends StatefulWidget {
  final Auth auth;
  const LoginScreen({super.key, required this.auth});
  @override
  State<LoginScreen> createState() => _LoginState();
}

class _LoginState extends State<LoginScreen> {
  final form = GlobalKey<FormState>();
  final username = TextEditingController(), password = TextEditingController();
  bool busy = false, obscure = true;
  String? error;
  @override
  void dispose() {
    username.dispose();
    password.dispose();
    super.dispose();
  }

  Future<void> login() async {
    if (!form.currentState!.validate()) return;
    setState(() {
      busy = true;
      error = null;
    });
    try {
      await widget.auth.login(username.text, password.text);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    body: Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
          colors: [navy, Color(0xffdce8fd), Color(0xfff9f7fc)],
        ),
      ),
      child: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.all(26),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 420),
              child: Column(
                children: [
                  const Brand(size: 140),
                  const SizedBox(height: 20),
                  const Text(
                    'Newsletter Manager',
                    style: TextStyle(
                      fontSize: 27,
                      fontWeight: FontWeight.w800,
                      color: navy,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Create, Automate & Manage\nTTW Newsletters',
                    textAlign: TextAlign.center,
                    style: TextStyle(color: navy),
                  ),
                  const SizedBox(height: 28),
                  Card(
                    child: Padding(
                      padding: const EdgeInsets.all(22),
                      child: Form(
                        key: form,
                        child: Column(
                          children: [
                            if (error ?? widget.auth.error
                                case final String message)
                              Notice(message),
                            TextFormField(
                              controller: username,
                              decoration: field(
                                'Username',
                                icon: Icons.mail_outline,
                              ),
                              autofillHints: const [AutofillHints.username],
                              validator: (v) => (v ?? '').trim().isEmpty
                                  ? 'Enter your username'
                                  : null,
                            ),
                            const SizedBox(height: 16),
                            TextFormField(
                              controller: password,
                              obscureText: obscure,
                              autofillHints: const [AutofillHints.password],
                              decoration:
                                  field(
                                    'Password',
                                    icon: Icons.lock_outline,
                                  ).copyWith(
                                    suffixIcon: IconButton(
                                      onPressed: () =>
                                          setState(() => obscure = !obscure),
                                      icon: Icon(
                                        obscure
                                            ? Icons.visibility_outlined
                                            : Icons.visibility_off_outlined,
                                      ),
                                    ),
                                  ),
                              validator: (v) => (v ?? '').isEmpty
                                  ? 'Enter your password'
                                  : null,
                              onFieldSubmitted: (_) => busy ? null : login(),
                            ),
                            const SizedBox(height: 24),
                            PrimaryButton(
                              'Login  →',
                              busy: busy,
                              onPressed: login,
                            ),
                          ],
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(height: 28),
                  const Text(
                    'TRAVEL AND TOUR WORLD',
                    style: TextStyle(
                      letterSpacing: 2,
                      fontSize: 11,
                      color: navy,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ),
      ),
    ),
  );
}
