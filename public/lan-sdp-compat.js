(function () {
  if (!window.RTCPeerConnection || window.__lanSdpCompatInstalled) return;
  window.__lanSdpCompatInstalled = true;

  function normalizeSdp(sdp) {
    return String(sdp || '').replace(/\r\n/g, '\n').replace(/\r/g, '\n');
  }

  function finalizeSdp(sdp) {
    return normalizeSdp(sdp).split('\n').join('\r\n');
  }

  function removeKnownProblemLines(sdp) {
    return finalizeSdp(normalizeSdp(sdp).split('\n').filter(function (line) {
      var trimmed = line.trim();
      return !/^a=(max-message-size|sctp-max-message-size):/.test(trimmed);
    }).join('\n'));
  }

  function convertDataChannelToLegacy(sdp) {
    var lines = normalizeSdp(sdp).split('\n');
    var sctpPort = '5000';

    lines.forEach(function (line) {
      var match = line.trim().match(/^a=sctp-port:(\d+)/);
      if (match) sctpPort = match[1];
    });

    var pendingSctpMap = false;
    var converted = [];

    lines.forEach(function (line) {
      var trimmed = line.trim();
      var mediaMatch = trimmed.match(/^m=application\s+(\S+)\s+UDP\/DTLS\/SCTP\s+webrtc-datachannel$/);

      if (mediaMatch) {
        converted.push('m=application ' + mediaMatch[1] + ' DTLS/SCTP ' + sctpPort);
        pendingSctpMap = true;
        return;
      }

      if (pendingSctpMap && /^a=mid:/.test(trimmed)) {
        converted.push(line);
        converted.push('a=sctpmap:' + sctpPort + ' webrtc-datachannel 1024');
        pendingSctpMap = false;
        return;
      }

      if (/^a=(max-message-size|sctp-port|sctp-max-message-size):/.test(trimmed)) return;

      converted.push(line);
    });

    if (pendingSctpMap) converted.push('a=sctpmap:' + sctpPort + ' webrtc-datachannel 1024');

    return finalizeSdp(converted.join('\n'));
  }

  function uniqueDescriptions(description) {
    var type = typeof description === 'string' ? 'offer' : description.type;
    var sdp = typeof description === 'string' ? description : description.sdp;
    var variants = [sdp, removeKnownProblemLines(sdp), convertDataChannelToLegacy(sdp)];
    var seen = Object.create(null);

    return variants.filter(function (variant) {
      if (!variant || seen[variant]) return false;
      seen[variant] = true;
      return true;
    }).map(function (variant) {
      return { type: type, sdp: variant };
    });
  }

  var originalSetRemoteDescription = window.RTCPeerConnection.prototype.setRemoteDescription;
  window.RTCPeerConnection.prototype.setRemoteDescription = async function (description) {
    if (!description || !description.sdp && typeof description !== 'string') {
      return originalSetRemoteDescription.call(this, description);
    }

    var attempts = uniqueDescriptions(description);
    var lastError = null;

    for (var i = 0; i < attempts.length; i += 1) {
      try {
        return await originalSetRemoteDescription.call(this, new RTCSessionDescription(attempts[i]));
      } catch (error) {
        lastError = error;
        if (!/SDP|SessionDescription|sctp|mid|max-message-size/i.test(String(error && error.message || error))) {
          throw error;
        }
      }
    }

    throw lastError;
  };
})();
