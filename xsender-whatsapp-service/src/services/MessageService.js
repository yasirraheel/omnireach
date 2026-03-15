import SessionManager from '../core/SessionManager.js';
import logger from '../utils/logger.js';
import { formatPhone, formatReceiver, isGroup } from '../utils/phoneFormatter.js';
import { delay } from '../utils/retry.js';
import { downloadMediaMessage } from '@whiskeysockets/baileys';
import axios from 'axios';
import fs from 'fs';
import path from 'path';
import { nanoid } from 'nanoid';
import baileysConfig from '../config/baileys.config.js';

// Memory-optimized axios instance for media downloads on cPanel
const mediaAxios = axios.create({
  timeout: 60000, // 60 second timeout
  maxContentLength: 10 * 1024 * 1024, // 10MB max
  maxBodyLength: 10 * 1024 * 1024, // 10MB max
  responseType: 'arraybuffer',
  // Disable automatic decompression to save memory
  decompress: true,
});

/**
 * Message Service - Handles all message operations
 *
 * Enterprise Anti-Ban Protection:
 * - Random delays between messages (configurable min/max)
 * - Extended pauses after batch counts
 * - Typing indicators simulation
 * - Read receipts simulation
 * - Message queue management
 * - Daily/hourly limits tracking
 */
class MessageService {
  constructor() {
    // Track message counts per session for anti-ban protection
    this.sessionCounters = new Map();

    // Anti-ban configuration defaults (can be overridden per request)
    this.antiBanDefaults = {
      minDelay: 1,           // Minimum delay between messages (seconds)
      maxDelay: 3,           // Maximum delay between messages (seconds)
      delayAfterCount: 50,   // Apply extended delay after this many messages
      delayAfterDuration: 30, // Extended delay duration (seconds)
      resetAfterCount: 200,  // Reset counter after this many messages
      simulateTyping: true,  // Simulate typing indicator
      typingDuration: 2000,  // Typing indicator duration (ms)
      maxMessagesPerHour: 60, // Maximum messages per hour (recommended)
      maxMessagesPerDay: 200, // Maximum messages per day (recommended)
    };
  }

  /**
   * Get or initialize session counter for anti-ban tracking
   */
  getSessionCounter(sessionId) {
    if (!this.sessionCounters.has(sessionId)) {
      this.sessionCounters.set(sessionId, {
        count: 0,
        hourlyCount: 0,
        dailyCount: 0,
        lastReset: Date.now(),
        lastHourReset: Date.now(),
        lastDayReset: Date.now(),
      });
    }

    const counter = this.sessionCounters.get(sessionId);
    const now = Date.now();

    // Reset hourly counter
    if (now - counter.lastHourReset > 3600000) {
      counter.hourlyCount = 0;
      counter.lastHourReset = now;
    }

    // Reset daily counter
    if (now - counter.lastDayReset > 86400000) {
      counter.dailyCount = 0;
      counter.lastDayReset = now;
    }

    return counter;
  }

  /**
   * Calculate anti-ban delay based on configuration
   * Returns delay in milliseconds
   */
  calculateAntiBanDelay(sessionId, config = {}) {
    const settings = { ...this.antiBanDefaults, ...config };
    const counter = this.getSessionCounter(sessionId);

    counter.count++;
    counter.hourlyCount++;
    counter.dailyCount++;

    // Calculate base random delay
    const minMs = settings.minDelay * 1000;
    const maxMs = settings.maxDelay * 1000;
    let delayMs = Math.floor(Math.random() * (maxMs - minMs + 1)) + minMs;

    // Apply extended delay after batch count
    if (settings.delayAfterCount > 0 && counter.count % settings.delayAfterCount === 0) {
      const extendedMs = settings.delayAfterDuration * 1000;
      delayMs += extendedMs;

      logger.info(`Anti-ban: Extended delay applied`, {
        sessionId,
        messageCount: counter.count,
        totalDelay: delayMs,
        reason: `Every ${settings.delayAfterCount} messages`,
      });
    }

    // Reset counter if threshold reached
    if (settings.resetAfterCount > 0 && counter.count >= settings.resetAfterCount) {
      counter.count = 0;
      counter.lastReset = Date.now();

      logger.info(`Anti-ban: Counter reset`, {
        sessionId,
        resetAfterCount: settings.resetAfterCount,
      });
    }

    // Warn if approaching limits
    if (counter.hourlyCount > settings.maxMessagesPerHour * 0.8) {
      logger.warn(`Anti-ban: Approaching hourly limit`, {
        sessionId,
        hourlyCount: counter.hourlyCount,
        limit: settings.maxMessagesPerHour,
      });
    }

    if (counter.dailyCount > settings.maxMessagesPerDay * 0.8) {
      logger.warn(`Anti-ban: Approaching daily limit`, {
        sessionId,
        dailyCount: counter.dailyCount,
        limit: settings.maxMessagesPerDay,
      });
    }

    return delayMs;
  }

  /**
   * Simulate typing indicator for more human-like behavior
   */
  async simulateTyping(client, jid, duration = 2000) {
    try {
      await client.socket.sendPresenceUpdate('composing', jid);
      await delay(duration);
      await client.socket.sendPresenceUpdate('paused', jid);
    } catch (error) {
      // Non-critical, just log and continue
      logger.debug(`Could not simulate typing: ${error.message}`);
    }
  }

  /**
   * Mark chat as read for more natural behavior
   */
  async markAsRead(client, jid) {
    try {
      await client.socket.readMessages([{ remoteJid: jid }]);
    } catch (error) {
      // Non-critical, just log
      logger.debug(`Could not mark as read: ${error.message}`);
    }
  }
  /**
   * Check if socket is actually connected and healthy
   */
  isSocketHealthy(client) {
    if (!client || !client.socket) {
      return false;
    }

    // Check if we have a user (authenticated) - this is the most reliable indicator
    if (!client.socket.user) {
      return false;
    }

    // Check WebSocket ready state if available
    // Note: After Baileys updates, the ws object structure may vary
    try {
      const ws = client.socket.ws;
      if (ws && typeof ws.readyState !== 'undefined') {
        // 0 = CONNECTING, 1 = OPEN, 2 = CLOSING, 3 = CLOSED
        if (ws.readyState === 3) { // CLOSED
          return false;
        }
      }
    } catch (e) {
      // If we can't check WebSocket state, rely on user presence
      logger.debug(`Could not check WebSocket state: ${e.message}`);
    }

    return true;
  }

  /**
   * Verify session is healthy before operations
   * If session is disconnected but has valid credentials, attempts auto-reconnect
   * Returns the client if healthy, throws error otherwise
   */
  async verifySession(sessionId) {
    let client = SessionManager.get(sessionId);

    // If client exists and is fully healthy, return immediately
    if (client && client.socket && client.socket.user) {
      if (!this.isSocketHealthy(client)) {
        logger.warn(`Session ${sessionId} appears unhealthy, but attempting operation anyway`);
      }
      return client;
    }

    // Session is unhealthy — attempt auto-reconnect if credentials are valid
    const reconnected = await this.tryAutoReconnect(sessionId, client);
    if (reconnected) {
      client = SessionManager.get(sessionId);
      if (client && client.socket && client.socket.user) {
        logger.info(`Auto-reconnect succeeded for session: ${sessionId}, proceeding with message`);
        return client;
      }
    }

    // Auto-reconnect failed or not possible
    if (!client || !client.socket) {
      throw new Error('Session not found or not connected');
    }
    if (!client.socket.user) {
      throw new Error('Session is not authenticated. Please reconnect via QR code.');
    }

    return client;
  }

  /**
   * Try to auto-reconnect a disconnected session
   * Used when a message send finds the session disconnected but credentials exist
   * Returns true if reconnection was initiated and session became healthy
   */
  async tryAutoReconnect(sessionId, client) {
    try {
      // If already reconnecting, wait for it to complete
      if (client && client.isReconnecting) {
        logger.info(`Session ${sessionId} is already reconnecting, waiting...`);
        return await this.waitForReconnect(sessionId, 15000);
      }

      // Check if client has valid credentials for reconnection
      if (client) {
        const hasValidCreds =
          (client.authState?.creds?.registered === true) ||
          (client.authState?.creds?.me !== undefined);

        if (!hasValidCreds) {
          logger.info(`Session ${sessionId} has no valid credentials for auto-reconnect`);
          return false;
        }

        // Has valid credentials — trigger reconnection
        const callbacks = client.savedCallbacks;
        if (!callbacks) {
          logger.info(`Session ${sessionId} has no saved callbacks, cannot auto-reconnect`);
          return false;
        }

        logger.info(`Auto-reconnecting session ${sessionId} (triggered by message send)`);

        // Close existing dead socket
        if (client.socket) {
          try { client.socket.end(undefined); } catch (e) { /* ignore */ }
          client.socket = null;
        }

        // Reset state for clean reconnection
        client.retryCount = 0;
        client.isReconnecting = false;
        client.consecutiveFailures = 0;

        // Reinitialize with saved callbacks
        await client.initialize(callbacks);

        // Wait for connection to establish
        return await this.waitForReconnect(sessionId, 20000);
      }

      // Client not in memory — check if session files exist and restore
      const fs = await import('fs');
      const path = await import('path');
      const baileysConfig = (await import('../config/baileys.config.js')).default;

      const sessionPath = path.default.join(baileysConfig.storage.sessionsPath, `md_${sessionId}`);
      const credsPath = path.default.join(sessionPath, 'creds.json');

      if (fs.default.existsSync(credsPath)) {
        logger.info(`Session ${sessionId} has files on disk, restoring...`);
        await SessionManager.create(sessionId);
        return await this.waitForReconnect(sessionId, 20000);
      }

      return false;
    } catch (error) {
      logger.error(`Auto-reconnect failed for session ${sessionId}: ${error.message}`);
      return false;
    }
  }

  /**
   * Wait for a session to become healthy within a timeout
   */
  async waitForReconnect(sessionId, timeoutMs) {
    const startTime = Date.now();
    const checkInterval = 2000;

    while (Date.now() - startTime < timeoutMs) {
      await delay(checkInterval);

      const client = SessionManager.get(sessionId);
      if (client && client.socket && client.socket.user) {
        return true;
      }
    }

    return false;
  }

  /**
   * Check if number exists on WhatsApp with timeout protection
   */
  async checkNumberExists(sessionId, number) {
    try {
      const client = await this.verifySession(sessionId);

      const formattedNumber = formatPhone(number);

      // Add timeout for the onWhatsApp call (15 seconds instead of default 60)
      const timeoutPromise = new Promise((_, reject) => {
        setTimeout(() => reject(new Error('Number check timed out')), 15000);
      });

      const checkPromise = client.socket.onWhatsApp(formattedNumber);
      const [result] = await Promise.race([checkPromise, timeoutPromise]);

      return {
        exists: result?.exists || false,
        jid: result?.jid || null,
      };
    } catch (error) {
      logger.error(`Failed to check number: ${error.message}`, { sessionId, number });
      throw error;
    }
  }

  /**
   * Send text message with enterprise anti-ban protection
   *
   * @param {string} sessionId - WhatsApp session ID
   * @param {string} receiver - Recipient phone number
   * @param {string} text - Message text
   * @param {number} delayMs - Optional delay (if 0, uses calculated anti-ban delay)
   * @param {object} antiBanConfig - Optional anti-ban configuration from Laravel gateway settings
   */
  async sendTextMessage(sessionId, receiver, text, delayMs = 0, antiBanConfig = null) {
    try {
      // Verify session is healthy first
      const client = await this.verifySession(sessionId);

      // Use formatReceiver to auto-detect group vs individual
      const formattedReceiver = formatReceiver(receiver);
      const isGroupMessage = isGroup(receiver);

      // Only check if number exists for individual messages (not groups)
      if (!isGroupMessage) {
        const check = await this.checkNumberExists(sessionId, receiver);
        if (!check.exists) {
          throw new Error('The receiver number does not exist on WhatsApp');
        }
      }

      // Calculate anti-ban delay if not provided
      let actualDelay = delayMs;
      if (antiBanConfig) {
        actualDelay = this.calculateAntiBanDelay(sessionId, antiBanConfig);
      } else if (delayMs === 0) {
        actualDelay = this.calculateAntiBanDelay(sessionId);
      }

      // Apply delay before sending
      if (actualDelay > 0) {
        logger.debug(`Anti-ban: Waiting ${actualDelay}ms before sending`, { sessionId, receiver: formattedReceiver });
        await delay(actualDelay);
      }

      // Simulate typing for more human-like behavior (if enabled)
      const config = antiBanConfig || this.antiBanDefaults;
      if (config.simulateTyping !== false) {
        await this.simulateTyping(client, formattedReceiver, config.typingDuration || 2000);
      }

      // Send message with timeout protection (30 seconds)
      const sendPromise = client.socket.sendMessage(formattedReceiver, {
        text: text,
      });

      const timeoutPromise = new Promise((_, reject) => {
        setTimeout(() => reject(new Error('Message send timed out')), 30000);
      });

      const result = await Promise.race([sendPromise, timeoutPromise]);

      // Get counter stats for logging
      const counter = this.getSessionCounter(sessionId);

      logger.info(`Text message sent successfully`, {
        sessionId,
        receiver: formattedReceiver,
        messageId: result.key.id,
        antiBan: {
          delayApplied: actualDelay,
          sessionMessageCount: counter.count,
          hourlyCount: counter.hourlyCount,
          dailyCount: counter.dailyCount,
        },
      });

      return {
        message: 'The message has been successfully sent',
        messageId: result.key.id,
        timestamp: result.messageTimestamp,
        antiBanStats: {
          delayApplied: actualDelay,
          messageCount: counter.count,
          hourlyCount: counter.hourlyCount,
          dailyCount: counter.dailyCount,
        },
      };
    } catch (error) {
      logger.error(`Failed to send text message: ${error.message}`, { sessionId, receiver });
      throw error;
    }
  }

  /**
   * Send image message
   */
  async sendImageMessage(sessionId, receiver, imageUrl, caption = '', delayMs = 0) {
    try {
      // Verify session is healthy first (same as text messages)
      const client = await this.verifySession(sessionId);

      // Use formatReceiver to auto-detect group vs individual
      const formattedReceiver = formatReceiver(receiver);
      const isGroupMessage = isGroup(receiver);

      // Only check if number exists for individual messages (not groups)
      if (!isGroupMessage) {
        const check = await this.checkNumberExists(sessionId, receiver);
        if (!check.exists) {
          throw new Error('The receiver number does not exist on WhatsApp');
        }
      }

      // Download image if URL provided
      let imageBuffer;
      try {
        if (imageUrl.startsWith('http')) {
          const response = await mediaAxios.get(imageUrl);
          imageBuffer = Buffer.from(response.data);
        } else if (imageUrl.startsWith('data:image')) {
          // Base64 image
          const base64Data = imageUrl.split(',')[1];
          imageBuffer = Buffer.from(base64Data, 'base64');
        } else {
          throw new Error('Invalid image URL or base64 format');
        }
      } catch (downloadError) {
        if (downloadError.message?.includes('maxContentLength') || downloadError.message?.includes('memory')) {
          throw new Error('Image file too large for server memory. Maximum 10MB allowed.');
        }
        throw new Error(`Failed to download image: ${downloadError.message}`);
      }

      // Apply delay
      if (delayMs > 0) {
        await delay(delayMs);
      }

      // Simulate typing for more natural behavior
      await this.simulateTyping(client, formattedReceiver, 1500);

      // Send image with timeout protection (60 seconds for media)
      const sendPromise = client.socket.sendMessage(formattedReceiver, {
        image: imageBuffer,
        caption: caption,
      });

      const timeoutPromise = new Promise((_, reject) => {
        setTimeout(() => reject(new Error('Image send timed out')), 60000);
      });

      const result = await Promise.race([sendPromise, timeoutPromise]);

      logger.info(`Image message sent successfully`, {
        sessionId,
        receiver: formattedReceiver,
        messageId: result.key.id,
        imageSize: imageBuffer.length,
      });

      return {
        message: 'Image message sent successfully',
        messageId: result.key.id,
        timestamp: result.messageTimestamp,
      };
    } catch (error) {
      logger.error(`Failed to send image message: ${error.message}`, { sessionId, receiver });
      throw error;
    }
  }

  /**
   * Send video message
   */
  async sendVideoMessage(sessionId, receiver, videoUrl, caption = '', delayMs = 0) {
    try {
      // Verify session is healthy first
      const client = await this.verifySession(sessionId);

      // Use formatReceiver to auto-detect group vs individual
      const formattedReceiver = formatReceiver(receiver);
      const isGroupMessage = isGroup(receiver);

      // Only check if number exists for individual messages (not groups)
      if (!isGroupMessage) {
        const check = await this.checkNumberExists(sessionId, receiver);
        if (!check.exists) {
          throw new Error('The receiver number does not exist on WhatsApp');
        }
      }

      // Download video
      let videoBuffer;
      try {
        if (videoUrl.startsWith('http')) {
          const response = await mediaAxios.get(videoUrl);
          videoBuffer = Buffer.from(response.data);
        } else {
          throw new Error('Invalid video URL');
        }
      } catch (downloadError) {
        if (downloadError.message?.includes('maxContentLength') || downloadError.message?.includes('memory')) {
          throw new Error('Video file too large for server memory. Maximum 10MB allowed.');
        }
        throw new Error(`Failed to download video: ${downloadError.message}`);
      }

      // Apply delay
      if (delayMs > 0) {
        await delay(delayMs);
      }

      // Simulate typing for more natural behavior
      await this.simulateTyping(client, formattedReceiver, 2000);

      // Send video with timeout protection (90 seconds for video)
      const sendPromise = client.socket.sendMessage(formattedReceiver, {
        video: videoBuffer,
        caption: caption,
      });

      const timeoutPromise = new Promise((_, reject) => {
        setTimeout(() => reject(new Error('Video send timed out')), 90000);
      });

      const result = await Promise.race([sendPromise, timeoutPromise]);

      logger.info(`Video message sent successfully`, {
        sessionId,
        receiver: formattedReceiver,
        messageId: result.key.id,
        videoSize: videoBuffer.length,
      });

      return {
        message: 'Video message sent successfully',
        messageId: result.key.id,
        timestamp: result.messageTimestamp,
      };
    } catch (error) {
      logger.error(`Failed to send video message: ${error.message}`, { sessionId, receiver });
      throw error;
    }
  }

  /**
   * Send audio message
   * Note: WhatsApp doesn't support captions on audio messages
   * If caption is provided, it will be sent as a separate text message first
   */
  async sendAudioMessage(sessionId, receiver, audioUrl, caption = '', delayMs = 0) {
    try {
      // Verify session is healthy first
      const client = await this.verifySession(sessionId);

      // Use formatReceiver to auto-detect group vs individual
      const formattedReceiver = formatReceiver(receiver);
      const isGroupMessage = isGroup(receiver);

      // Only check if number exists for individual messages (not groups)
      if (!isGroupMessage) {
        const check = await this.checkNumberExists(sessionId, receiver);
        if (!check.exists) {
          throw new Error('The receiver number does not exist on WhatsApp');
        }
      }

      // Download audio
      let audioBuffer;
      try {
        if (audioUrl.startsWith('http')) {
          const response = await mediaAxios.get(audioUrl);
          audioBuffer = Buffer.from(response.data);
        } else {
          throw new Error('Invalid audio URL');
        }
      } catch (downloadError) {
        if (downloadError.message?.includes('maxContentLength') || downloadError.message?.includes('memory')) {
          throw new Error('Audio file too large for server memory. Maximum 10MB allowed.');
        }
        throw new Error(`Failed to download audio: ${downloadError.message}`);
      }

      // Apply delay
      if (delayMs > 0) {
        await delay(delayMs);
      }

      // WhatsApp doesn't support captions on audio messages
      // Send text message first if caption provided
      if (caption && caption.trim()) {
        try {
          await client.socket.sendMessage(formattedReceiver, { text: caption });
          logger.info(`Caption sent as separate text before audio`, { sessionId, receiver: formattedReceiver });
          // Small delay between text and audio
          await delay(500);
        } catch (textError) {
          logger.warn(`Failed to send caption text: ${textError.message}`);
          // Continue with audio even if text fails
        }
      }

      // Send audio with timeout protection (60 seconds)
      const sendPromise = client.socket.sendMessage(formattedReceiver, {
        audio: audioBuffer,
        mimetype: 'audio/mpeg',
        ptt: false, // Set to true for voice note
      });

      const timeoutPromise = new Promise((_, reject) => {
        setTimeout(() => reject(new Error('Audio send timed out')), 60000);
      });

      const result = await Promise.race([sendPromise, timeoutPromise]);

      logger.info(`Audio message sent successfully`, {
        sessionId,
        receiver: formattedReceiver,
        messageId: result.key.id,
        audioSize: audioBuffer.length,
        hadCaption: !!caption,
      });

      return {
        message: 'Audio message sent successfully',
        messageId: result.key.id,
        timestamp: result.messageTimestamp,
      };
    } catch (error) {
      logger.error(`Failed to send audio message: ${error.message}`, { sessionId, receiver });
      throw error;
    }
  }

  /**
   * Send document message with caption
   */
  async sendDocumentMessage(sessionId, receiver, documentUrl, filename, mimetype, caption = '', delayMs = 0) {
    try {
      // Verify session is healthy first
      const client = await this.verifySession(sessionId);

      // Use formatReceiver to auto-detect group vs individual
      const formattedReceiver = formatReceiver(receiver);
      const isGroupMessage = isGroup(receiver);

      // Only check if number exists for individual messages (not groups)
      if (!isGroupMessage) {
        const check = await this.checkNumberExists(sessionId, receiver);
        if (!check.exists) {
          throw new Error('The receiver number does not exist on WhatsApp');
        }
      }

      // Download document
      let documentBuffer;
      try {
        if (documentUrl.startsWith('http')) {
          const response = await mediaAxios.get(documentUrl);
          documentBuffer = Buffer.from(response.data);
        } else {
          throw new Error('Invalid document URL');
        }
      } catch (downloadError) {
        if (downloadError.message?.includes('maxContentLength') || downloadError.message?.includes('memory')) {
          throw new Error('Document file too large for server memory. Maximum 10MB allowed.');
        }
        throw new Error(`Failed to download document: ${downloadError.message}`);
      }

      // Apply delay
      if (delayMs > 0) {
        await delay(delayMs);
      }

      // Send document with timeout protection (60 seconds)
      const messageContent = {
        document: documentBuffer,
        fileName: filename,
        mimetype: mimetype || 'application/pdf',
      };

      // Add caption if provided
      if (caption && caption.trim()) {
        messageContent.caption = caption;
      }

      const sendPromise = client.socket.sendMessage(formattedReceiver, messageContent);

      const timeoutPromise = new Promise((_, reject) => {
        setTimeout(() => reject(new Error('Document send timed out')), 60000);
      });

      const result = await Promise.race([sendPromise, timeoutPromise]);

      logger.info(`Document message sent successfully`, {
        sessionId,
        receiver: formattedReceiver,
        messageId: result.key.id,
        documentSize: documentBuffer.length,
      });

      return {
        message: 'Document message sent successfully',
        messageId: result.key.id,
        timestamp: result.messageTimestamp,
      };
    } catch (error) {
      logger.error(`Failed to send document message: ${error.message}`, { sessionId, receiver });
      throw error;
    }
  }

  /**
   * Send button message
   * Note: WhatsApp has deprecated interactive buttons for non-business accounts.
   * This method now sends buttons as a formatted text message with numbered options.
   * For true interactive buttons, use WhatsApp Business API (Cloud API).
   */
  async sendButtonMessage(sessionId, receiver, text, buttons, footer = '', delayMs = 0) {
    try {
      // Verify session is healthy first
      const client = await this.verifySession(sessionId);

      // Use formatReceiver to auto-detect group vs individual
      const formattedReceiver = formatReceiver(receiver);
      const isGroupMessage = isGroup(receiver);

      // Only check if number exists for individual messages (not groups)
      if (!isGroupMessage) {
        const check = await this.checkNumberExists(sessionId, receiver);
        if (!check.exists) {
          throw new Error('The receiver number does not exist on WhatsApp');
        }
      }

      // Apply delay
      if (delayMs > 0) {
        await delay(delayMs);
      }

      // Since WhatsApp deprecated buttons for non-business accounts,
      // we send as a formatted text message with numbered options
      let formattedText = text + '\n\n';

      // Add buttons as numbered options
      buttons.forEach((btn, index) => {
        formattedText += `${index + 1}. ${btn.text}\n`;
      });

      // Add footer if provided
      if (footer) {
        formattedText += `\n_${footer}_`;
      }

      // Add instruction
      formattedText += '\n\n_Reply with the number of your choice_';

      const result = await client.socket.sendMessage(formattedReceiver, {
        text: formattedText,
      });

      logger.info(`Button message sent as formatted text`, {
        sessionId,
        receiver: formattedReceiver,
        messageId: result.key.id,
        buttonCount: buttons.length,
      });

      return {
        message: 'Button message sent successfully (as formatted text)',
        messageId: result.key.id,
        timestamp: result.messageTimestamp,
        note: 'Interactive buttons are deprecated by WhatsApp for non-business accounts. Message sent as numbered options.',
      };
    } catch (error) {
      logger.error(`Failed to send button message: ${error.message}`, { sessionId, receiver });
      throw error;
    }
  }

  /**
   * Send list/template message
   * Note: WhatsApp has deprecated interactive list messages for non-business accounts.
   * This method now sends lists as a formatted text message with sections and options.
   * For true interactive lists, use WhatsApp Business API (Cloud API).
   */
  async sendListMessage(sessionId, receiver, title, text, buttonText, sections, footer = '', delayMs = 0) {
    try {
      // Verify session is healthy first
      const client = await this.verifySession(sessionId);

      // Use formatReceiver to auto-detect group vs individual
      const formattedReceiver = formatReceiver(receiver);
      const isGroupMessage = isGroup(receiver);

      // Only check if number exists for individual messages (not groups)
      if (!isGroupMessage) {
        const check = await this.checkNumberExists(sessionId, receiver);
        if (!check.exists) {
          throw new Error('The receiver number does not exist on WhatsApp');
        }
      }

      // Apply delay
      if (delayMs > 0) {
        await delay(delayMs);
      }

      // Since WhatsApp deprecated list messages for non-business accounts,
      // we send as a formatted text message with sections
      let formattedText = '';

      // Add title if provided
      if (title) {
        formattedText += `*${title}*\n\n`;
      }

      // Add main text
      formattedText += text + '\n\n';

      // Add sections with numbered options
      let optionNumber = 1;
      sections.forEach((section) => {
        if (section.title) {
          formattedText += `📋 *${section.title}*\n`;
        }
        if (section.rows && Array.isArray(section.rows)) {
          section.rows.forEach((row) => {
            formattedText += `${optionNumber}. ${row.title}`;
            if (row.description) {
              formattedText += ` - _${row.description}_`;
            }
            formattedText += '\n';
            optionNumber++;
          });
        }
        formattedText += '\n';
      });

      // Add footer if provided
      if (footer) {
        formattedText += `_${footer}_\n`;
      }

      // Add instruction
      formattedText += '\n_Reply with the number of your choice_';

      const result = await client.socket.sendMessage(formattedReceiver, {
        text: formattedText,
      });

      logger.info(`List message sent as formatted text`, {
        sessionId,
        receiver: formattedReceiver,
        messageId: result.key.id,
        sectionCount: sections.length,
      });

      return {
        message: 'List message sent successfully (as formatted text)',
        messageId: result.key.id,
        timestamp: result.messageTimestamp,
        note: 'Interactive lists are deprecated by WhatsApp for non-business accounts. Message sent as formatted options.',
      };
    } catch (error) {
      logger.error(`Failed to send list message: ${error.message}`, { sessionId, receiver });
      throw error;
    }
  }

  /**
   * Send bulk messages
   */
  async sendBulkMessages(sessionId, messages) {
    try {
      // Verify session is healthy first
      const client = await this.verifySession(sessionId);

      const results = [];
      const errors = [];

      for (let i = 0; i < messages.length; i++) {
        const msg = messages[i];

        try {
          const result = await this.sendTextMessage(
            sessionId,
            msg.receiver,
            msg.message.text,
            msg.delay || baileysConfig.message.bulkDelay
          );

          results.push({
            index: i,
            receiver: msg.receiver,
            success: true,
            messageId: result.messageId,
          });
        } catch (error) {
          errors.push({
            index: i,
            receiver: msg.receiver,
            error: error.message,
          });
        }
      }

      const sent = results.length;
      const failed = errors.length;
      const isAllFailed = failed === messages.length;

      return {
        message: isAllFailed
          ? 'Failed to send all messages'
          : failed > 0
            ? 'Some messages have been successfully sent'
            : 'All messages have been successfully sent',
        sent,
        failed,
        results,
        errors,
      };
    } catch (error) {
      logger.error(`Failed to send bulk messages: ${error.message}`, { sessionId });
      throw error;
    }
  }
}

export default new MessageService();
