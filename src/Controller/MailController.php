<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\TokenGenerator\TokenGeneratorInterface;

#[Route('/api/mail')]
class MailController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private TokenGeneratorInterface $tokenGenerator,
        private UserPasswordHasherInterface $passwordHasher
    ) {
    }

    #[Route('/forgot-password', name: 'mail_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['email'])) {
            return new JsonResponse(['error' => 'Email requis'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $data['email']]);

        if (!$user) {
            return new JsonResponse(['message' => 'Si cette adresse email existe, un email de réinitialisation a été envoyé'], Response::HTTP_OK);
        }

        // Génération d'un token sécurisé plus long
        $token = bin2hex(random_bytes(32)); // 64 caractères hexadécimaux
        $user->setResetToken($token);
        $user->setResetTokenExpiresAt(new \DateTime('+1 hour'));

        $this->entityManager->flush();

        $email = (new Email())
            ->from('trulesdoniphane974@gmail.com')
            ->to($user->getEmail())
            ->subject('Réinitialisation de votre mot de passe - AcadyoQuizz')
            ->html($this->generateResetEmailTemplate($user->getFirstName(), $token));

        try {
            $this->mailer->send($email);
            return new JsonResponse(['message' => 'Email de réinitialisation envoyé'], Response::HTTP_OK);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Erreur lors de l\'envoi de l\'email'], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    #[Route('/reset-password', name: 'mail_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['token']) || !isset($data['password'])) {
            return new JsonResponse(['error' => 'Token et nouveau mot de passe requis'], Response::HTTP_BAD_REQUEST);
        }

        // Validation supplémentaire du token (doit être un token hexadécimal de 64 caractères)
        if (!preg_match('/^[a-f0-9]{64}$/', $data['token'])) {
            return new JsonResponse(['error' => 'Format de token invalide'], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['resetToken' => $data['token']]);

        if (!$user || !$user->getResetTokenExpiresAt() || $user->getResetTokenExpiresAt() < new \DateTime()) {
            return new JsonResponse(['error' => 'Token invalide ou expiré'], Response::HTTP_BAD_REQUEST);
        }

        $hashedPassword = $this->passwordHasher->hashPassword($user, $data['password']);
        $user->setPassword($hashedPassword);
        $user->setResetToken(null);
        $user->setResetTokenExpiresAt(null);

        $this->entityManager->flush();

        return new JsonResponse(['message' => 'Mot de passe réinitialisé avec succès'], Response::HTTP_OK);
    }

    private function generateResetEmailTemplate(string $firstName, string $token): string
    {
        // URL sécurisée pour la réinitialisation (frontend React - Vite port 5173)
        $resetUrl = 'http://localhost:5173/reset-password?token=' . urlencode($token);
        
        return '
        <html>
        <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
            <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
                <h2 style="color: #007bff;">Réinitialisation de votre mot de passe</h2>
                <p>Bonjour ' . htmlspecialchars($firstName) . ',</p>
                <p>Vous avez demandé la réinitialisation de votre mot de passe sur AcadyoQuizz.</p>
                <p>Cliquez sur le bouton ci-dessous pour réinitialiser votre mot de passe :</p>
                <div style="text-align: center; margin: 30px 0;">
                    <a href="' . htmlspecialchars($resetUrl) . '" 
                       style="background-color: #007bff; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; display: inline-block;">
                        Réinitialiser mon mot de passe
                    </a>
                </div>
                <p><strong>Ce lien expire dans 1 heure.</strong></p>
                <p>Si le bouton ne fonctionne pas, vous pouvez copier et coller ce lien dans votre navigateur :</p>
                <p style="word-break: break-all; background-color: #f8f9fa; padding: 10px; border-radius: 5px; font-size: 12px;">
                    ' . htmlspecialchars($resetUrl) . '
                </p>
                <p><strong>⚠️ Important :</strong> Si vous n\'avez pas demandé cette réinitialisation, veuillez ignorer cet email et votre mot de passe restera inchangé.</p>
                <hr style="margin: 30px 0; border: none; border-top: 1px solid #eee;">
                <p style="font-size: 12px; color: #666;">
                    L\'équipe AcadyoQuizz<br>
                    Cet email a été envoyé automatiquement, merci de ne pas y répondre.
                </p>
            </div>
        </body>
        </html>';
    }
}