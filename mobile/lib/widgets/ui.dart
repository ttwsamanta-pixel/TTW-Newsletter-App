import 'package:flutter/material.dart';

const navy = Color(0xff202151);
const red = Color(0xffe81032);
const page = Color(0xfff6f6fc);

class Brand extends StatelessWidget {
  final double size;
  const Brand({super.key, this.size = 110});
  @override
  Widget build(BuildContext context) => Image.asset(
    'assets/ttw-logo.png',
    width: size,
    height: size,
    fit: BoxFit.contain,
  );
}

class PrimaryButton extends StatelessWidget {
  final String text;
  final VoidCallback? onPressed;
  final bool busy;
  const PrimaryButton(
    this.text, {
    super.key,
    this.onPressed,
    this.busy = false,
  });
  @override
  Widget build(BuildContext context) => SizedBox(
    width: double.infinity,
    height: 52,
    child: FilledButton(
      onPressed: busy ? null : onPressed,
      child: busy
          ? const SizedBox(
              width: 22,
              height: 22,
              child: CircularProgressIndicator(strokeWidth: 2),
            )
          : Text(text),
    ),
  );
}

class Notice extends StatelessWidget {
  final String text;
  final bool success;
  const Notice(this.text, {super.key, this.success = false});
  @override
  Widget build(BuildContext context) => Container(
    width: double.infinity,
    margin: const EdgeInsets.symmetric(vertical: 12),
    padding: const EdgeInsets.all(16),
    decoration: BoxDecoration(
      color: success ? const Color(0xffe0f8e3) : const Color(0xffffefdf),
      borderRadius: BorderRadius.circular(14),
    ),
    child: Text(
      text,
      style: TextStyle(
        color: success ? Colors.green.shade900 : Colors.brown.shade900,
      ),
    ),
  );
}

InputDecoration field(String label, {String? hint, IconData? icon}) =>
    InputDecoration(
      labelText: label,
      hintText: hint,
      prefixIcon: icon == null ? null : Icon(icon, size: 20),
    );

Future<void> showFailure(BuildContext context, Object error) async {
  if (!context.mounted) return;
  ScaffoldMessenger.of(context)
      .showSnackBar(SnackBar(content: Text(error.toString())));
}
