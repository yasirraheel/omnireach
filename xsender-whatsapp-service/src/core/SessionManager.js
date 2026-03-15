import WhatsAppClient from './WhatsAppClient.js';
import logger from '../utils/logger.js';
import fs from 'fs';
import path from 'path';
import baileysConfig from '../config/baileys.config.js';
import WebhookService from '../services/WebhookService.js';

/**
 * Session Manager - Manages multiple WhatsApp sessions
 */
class SessionManager {
  constructor() {
    this.sessions = new Map();
    this.retries = new Map();
  }

  /**
   * Check if session exists
   */
  exists(sessionId) {
    return this.sessions.has(sessionId);
  }

  /**
   * Get session
   */
  get(sessionId) {
    return this.sessions.get(sessionId) || null;
  }

  /**
   * Create new session
   */
  async create(sessionId, callbacks = {}) {
    try {
      if (this.exists(sessionId)) {
        throw new Error('Session already exists');
      }

      const client = new WhatsAppClient(sessionId);

      // Create message handler that forwards to Laravel
      const messageHandler = WebhookService.createMessageHandler(sessionId);

      // Initialize with callbacks
      await client.initialize({
        onQR: (qr) => {
          logger.info(`QR generated for session: ${sessionId}`);
          // Notify Laravel that QR is being shown
          WebhookService.notifySessionStatus(sessionId, 'qr', null);
          if (callbacks.onQR) {
            callbacks.onQR(qr);
          }
        },
        onConnected: (user) => {
          logger.info(`Session connected: ${sessionId}`, { user });
          // Notify Laravel that session is connected
          WebhookService.notifySessionStatus(sessionId, 'connected', user);
          if (callbacks.onConnected) {
            callbacks.onConnected(user);
          }
        },
        onDisconnected: (shouldReconnect, isLoggedOut = false) => {
          // Notify Laravel that session is disconnected
          const status = shouldReconnect ? 'disconnected' : (isLoggedOut ? 'logged_out' : 'disconnected');
          WebhookService.notifySessionStatus(sessionId, status, null);

          if (!shouldReconnect) {
            // Only delete session files if explicitly logged out
            // For other disconnects (badSession, errors), preserve files for reconnection
            if (isLoggedOut) {
              this.delete(sessionId, true);  // Delete with files
            } else {
              this.remove(sessionId);  // Just remove from memory, keep files
            }
          }
          if (callbacks.onDisconnected) {
            callbacks.onDisconnected(shouldReconnect);
          }
        },
        // Always attach message handler for incoming messages
        onMessage: async (msg) => {
          // Forward to Laravel webhook
          await messageHandler(msg);
          // Also call custom callback if provided
          if (callbacks.onMessage) {
            callbacks.onMessage(msg);
          }
        },
      });

      this.sessions.set(sessionId, client);
      this.retries.delete(sessionId);

      logger.info(`Session created: ${sessionId}`);
      return client;
    } catch (error) {
      logger.error(`Failed to create session: ${error.message}`, { sessionId });
      throw error;
    }
  }

  /**
   * Delete session - removes from memory and optionally deletes session files
   * @param {string} sessionId - Session ID to delete
   * @param {boolean} deleteFiles - Whether to delete session files (default: true for manual deletion)
   */
  async delete(sessionId, deleteFiles = true) {
    try {
      const client = this.get(sessionId);

      if (client) {
        if (deleteFiles) {
          // Full logout - removes session files
          await client.logout();
        } else {
          // Just disconnect - keep session files for reconnection
          await client.disconnect();
        }
        this.sessions.delete(sessionId);
        this.retries.delete(sessionId);
        logger.info(`Session deleted: ${sessionId}`, { filesDeleted: deleteFiles });
      }
    } catch (error) {
      logger.error(`Failed to delete session: ${error.message}`, { sessionId });
      throw error;
    }
  }

  /**
   * Remove session from memory without deleting files
   * Used when session disconnects but we want to preserve credentials for reconnection
   */
  async remove(sessionId) {
    try {
      const client = this.get(sessionId);

      if (client) {
        // Just stop timers and close socket, don't delete files
        client.stopPresenceTimer();
        if (client.socket) {
          try {
            client.socket.end(undefined);
          } catch (e) {
            // Ignore close errors
          }
          client.socket = null;
        }
        this.sessions.delete(sessionId);
        this.retries.delete(sessionId);
        logger.info(`Session removed from memory (files preserved): ${sessionId}`);
      }
    } catch (error) {
      logger.error(`Failed to remove session: ${error.message}`, { sessionId });
    }
  }

  /**
   * Get session status
   */
  getStatus(sessionId) {
    const client = this.get(sessionId);

    if (!client) {
      return { status: 'disconnected', isSession: false };
    }

    return client.getStatus();
  }

  /**
   * Get all sessions
   */
  getAll() {
    return Array.from(this.sessions.keys());
  }

  /**
   * Restore sessions on startup
   */
  async restoreSessions() {
    try {
      const sessionsPath = baileysConfig.storage.sessionsPath;

      if (!fs.existsSync(sessionsPath)) {
        fs.mkdirSync(sessionsPath, { recursive: true });
        // Sync empty sessions with Laravel
        await this.syncWithLaravel();
        return;
      }

      const files = fs.readdirSync(sessionsPath);

      for (const file of files) {
        // Only restore md_ prefixed directories
        if (!file.startsWith('md_') || file.endsWith('_store.json')) {
          continue;
        }

        const sessionId = file.replace('md_', '');
        const sessionPath = path.join(sessionsPath, file);

        // Check if it's a directory with creds.json
        const credsPath = path.join(sessionPath, 'creds.json');
        if (fs.existsSync(credsPath) && fs.statSync(sessionPath).isDirectory()) {
          try {
            logger.info(`Restoring session: ${sessionId}`);
            await this.create(sessionId);
          } catch (error) {
            logger.error(`Failed to restore session ${sessionId}: ${error.message}`);
          }
        }
      }

      logger.info(`Sessions restored. Active: ${this.sessions.size}`);

      // Sync all session statuses with Laravel after restore
      await this.syncWithLaravel();
    } catch (error) {
      logger.error(`Failed to restore sessions: ${error.message}`);
    }
  }

  /**
   * Sync all session statuses with Laravel
   * This ensures Laravel database is consistent with actual session states
   */
  async syncWithLaravel() {
    try {
      const sessions = [];

      for (const [sessionId, client] of this.sessions) {
        const status = client.getStatus();
        sessions.push({
          id: sessionId,
          status: status.isSession ? 'connected' : 'disconnected',
          user: status.user || null,
        });
      }

      logger.info(`Syncing ${sessions.length} sessions with Laravel`);
      await WebhookService.syncSessionsWithLaravel(sessions);
    } catch (error) {
      logger.error(`Failed to sync sessions with Laravel: ${error.message}`);
    }
  }

  /**
   * Cleanup all sessions
   */
  async cleanup() {
    logger.info('Cleaning up all sessions...');
    // Sessions will be cleaned up automatically on process exit
    logger.info('All sessions cleaned up');
  }
}

// Export singleton instance
export default new SessionManager();
