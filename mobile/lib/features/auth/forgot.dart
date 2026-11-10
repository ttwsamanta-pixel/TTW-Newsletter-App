import 'package:flutter/material.dart';

import '../../core/auth.dart';
import '../../widgets/ui.dart';

class ForgotPasswordScreen extends StatefulWidget {
  final Auth auth;
  const ForgotPasswordScreen({super.key, required this.auth});
  @override
  State<ForgotPasswordScreen> createState() => _ForgotState();
}

class _ForgotState extends State<ForgotPasswordScreen> {
  final email = TextEditingController();
  bool busy = false;
  String? message, error;
  @override
  void dispose() {
    email.dispose();
    super.dispose();
  }

  Future<void> send() async {
    if (email.text.trim().isEmpty) {
      setState(() => error = 'Enter your username or registered email.');
      return;
    }
    setState(() {
      busy = true;
      error = null;
    });
    try {
      final r = await widget.auth.api.request(
        '/auth/forgot-password',
        data: {'identifier': email.text.trim()},
      );
      if (mounted) setState(() => message = r['message']);
    } catch (e) {
      if (mounted) setState(() => error = e.toString());
    } finally {
      if (mounted) setState(() => busy = false);
    }
  }

  @override
  Widget build(BuildContext context) => Scaffold(
    appBar: AppBar(title: const Text('Forgot Password')),
    body: SafeArea(
      child: ListView(
        padding: const EdgeInsets.all(28),
        children: [
          const Brand(size: 110),
          const SizedBox(height: 36),
          const Text(
            'Reset your password',
            style: TextStyle(fontSize: 24, fontWeight: FontWeight.bold),
          ),
          const SizedBox(height: 16),
          const Text(
            'Enter your username or registered email address. We will send a password reset link when an email is registered for the account.',
          ),
          const SizedBox(height: 28),
          TextField(
            controller: email,
            decoration: field('Username / Email', icon: Icons.mail_outline),
            keyboardType: TextInputType.emailAddress,
          ),
          if (error != null) Notice(error!),
          if (message != null) Notice(message!, success: true),
          const SizedBox(height: 24),
          PrimaryButton('Send Reset Link', busy: busy, onPressed: send),
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Back to Login'),
          ),
        ],
      ),
    ),
  );
}
